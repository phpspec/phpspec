<?php

/*
 * This file is part of PhpSpec, A php toolset to drive emergent
 * design by specification.
 *
 * (c) Marcello Duarte <marcello.duarte@gmail.com>
 * (c) Konstantin Kudryashov <ever.zet@gmail.com>
 * (c) Ciaran McNulty <ciaran@ciaranmcnulty.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpSpec\Console\Command\Refactor;

use PhpSpec\Ai\Agent\Proposal;
use PhpSpec\Ai\Agent\Writer;
use PhpSpec\Ai\RefactorJournal;
use PhpSpec\Console\Consent;
use RuntimeException;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Output\OutputInterface as Output;

/**
 * @internal
 * Carries out a refactoring plan one step at a time: the model writes the
 * step, it is written once the developer says yes (or straight away when
 * nobody attends the run) and the whole suite checked. A step that breaks
 * more than it meant to, or leaves fewer examples than it found, gets one
 * fix; still wrong, or not fixed at all, it is undone. However the run
 * stops, it stops green: the steps kept since the suite last passed are
 * undone too, and the steps kept are noted in the journal.
 */
final readonly class StepByStep
{
    private const UNDOES = 'this will undo all changes until the last green state';

    /**
     * @param bool $attended whether somebody answers: they see the plan's checklist and each diff before saying yes, where a run nobody attends names the files it writes
     */
    public function __construct(
        private Output $output,
        private RefactorModel $model,
        private SpecSuite $suite,
        private Consent $consent,
        private Progress $progress,
        private Writer $writer,
        private RefactorJournal $journal,
        private bool $attended = true,
    ) {}

    /**
     * @param SuiteCheck $baseline the suite before the first step
     * @param string $class the class refactored, which the journal keeps the steps kept under
     */
    public function carryOut(RefactorPlan $plan, SuiteCheck $baseline, string $class): Carried
    {
        $carried = $this->stepThrough($plan, $baseline);

        if ($carried->kept !== []) {
            $this->journal->record($class, $plan->technique, implode('; ', $carried->kept));
        }

        return $carried;
    }

    private function stepThrough(RefactorPlan $plan, SuiteCheck $baseline): Carried
    {
        $examples = $baseline->ran();
        /** @var list<array{title: string, applied: list<Proposal>, green: bool}> $kept */
        $kept = [];

        foreach ($plan->steps as $index => $planned) {
            $number = $index + 1;
            $this->output->writeln('');

            try {
                $step = $this->progress->during($planned->doing, fn(): RefactorStep => $this->model->step($plan, $index));
            } catch (RefusedStepException|ModelFailedException $e) {
                $this->output->writeln('  <fg=red>' . OutputFormatter::escape($e->getMessage()) . '</>');

                return $this->stopped(1, sprintf('at step %d', $number), $kept, $e->getMessage());
            }

            if ($this->attended) {
                $this->progress->checklist($plan, $index);
            }

            $this->showFiles($step);
            $redSoFar = $this->sinceGreen($kept) !== [];

            if (!$this->consent->confirm($this->output, '  Apply this step?', 'refactor-step', 'apply the remaining refactoring steps', $redSoFar ? self::UNDOES : '')) {
                return $this->stopped(0, sprintf('before step %d', $number), $kept);
            }

            try {
                $standing = $this->standing($step, $number === count($plan->steps), $examples);
            } catch (RefusedStepException $e) {
                $this->output->writeln('  <fg=red>' . OutputFormatter::escape($e->getMessage()) . '</>');

                return $this->stopped(1, sprintf('at step %d', $number), $kept, $e->getMessage());
            }

            if ($standing['check'] === null) {
                return $this->stopped(1, sprintf('at step %d', $number), $kept, $standing['cause'], $number);
            }

            $examples = $standing['check']->ran();
            $kept[] = ['title' => $planned->title, 'applied' => $standing['applied'], 'green' => $standing['check']->green()];
        }

        $this->output->writeln('');
        $this->output->writeln(sprintf('  <fg=green>Refactoring done: %s ✓</>', OutputFormatter::escape($plan->technique)));

        return $this->carried(0, $kept);
    }

    /**
     * Stops the run green: the steps kept since the suite last passed are
     * undone, and the line says which steps were undone and which kept.
     *
     * @param list<array{title: string, applied: list<Proposal>, green: bool}> $kept
     * @param string $cause what stopped it, beyond the line, for a reader that saw none of the run
     * @param int|null $undoneNow the step undone where it stood, when one was
     */
    private function stopped(int $exitCode, string $where, array $kept, string $cause = '', ?int $undoneNow = null): Carried
    {
        $red = $this->sinceGreen($kept);

        foreach (array_reverse($red) as $step) {
            array_map($this->writer->revert(...), $step['applied']);
        }

        $kept = array_slice($kept, 0, count($kept) - count($red));
        $undone = $red === [] ? [] : range(count($kept) + 1, count($kept) + count($red));
        $undone = $undoneNow === null ? $undone : [...$undone, $undoneNow];
        $why = sprintf('Stopped %s: %s.', $where, $this->whatStands($undone, count($kept), $red !== []));
        $this->output->writeln('  ' . $why);

        return $this->carried($exitCode, $kept, trim($why . "\n" . $cause));
    }

    /**
     * @param list<int> $undone the numbers of the steps undone
     */
    private function whatStands(array $undone, int $kept, bool $backToGreen): string
    {
        $stands = [];

        if ($undone !== []) {
            $stands[] = sprintf('%s %s undone%s', $this->steps($undone), count($undone) === 1 ? 'is' : 'are', $backToGreen ? ', back to the last green state' : '');
        }

        if ($kept > 0) {
            $stands[] = sprintf('%s %s kept', $this->steps(range(1, $kept)), $kept === 1 ? 'is' : 'are');
        }

        return $stands === [] ? 'nothing was written' : implode('; ', $stands);
    }

    /**
     * @param non-empty-list<int> $numbers consecutive step numbers
     */
    private function steps(array $numbers): string
    {
        return match (count($numbers)) {
            1 => sprintf('step %d', $numbers[0]),
            2 => sprintf('steps %d and %d', $numbers[0], $numbers[1]),
            default => sprintf('steps %d to %d', $numbers[0], $numbers[count($numbers) - 1]),
        };
    }

    /**
     * The steps kept since the suite last passed: a step that wrote a spec
     * first, still red.
     *
     * @param list<array{title: string, applied: list<Proposal>, green: bool}> $kept
     * @return list<array{title: string, applied: list<Proposal>, green: bool}>
     */
    private function sinceGreen(array $kept): array
    {
        $red = [];

        foreach (array_reverse($kept) as $step) {
            if ($step['green']) {
                break;
            }

            array_unshift($red, $step);
        }

        return $red;
    }

    /**
     * @param list<array{title: string, applied: list<Proposal>, green: bool}> $kept
     */
    private function carried(int $exitCode, array $kept, string $stopped = ''): Carried
    {
        $files = [];

        foreach ($kept as $step) {
            foreach (array_reverse($step['applied']) as $file) {
                $files[] = $file->path;
            }
        }

        return new Carried($exitCode, array_column($kept, 'title'), array_values(array_unique($files)), $stopped);
    }

    /**
     * Writes the step and checks the suite, fixing it once when it does not
     * stand. The suite it stands on and what was written, or, when it was
     * undone, why.
     *
     * @param int $examples how many examples ran before the step
     * @return array{check: SuiteCheck, applied: list<Proposal>, cause: ''}|array{check: null, applied: list<Proposal>, cause: string}
     */
    private function standing(RefactorStep $step, bool $last, int $examples): array
    {
        $applied = $this->apply($step);
        $check = $this->suite->check();
        $wrong = $this->wrongWith($check, $step, $last, $examples);

        if ($wrong !== '') {
            $this->output->writeln($this->verdict($check, $step, $last) . ', asking for a fix...');
            $this->output->writeln($this->indented($wrong));
            $this->output->writeln('');

            try {
                $step = $this->progress->during('Fixing it', fn(): RefactorStep => $this->model->fix($step, $wrong));
            } catch (RefusedStepException|ModelFailedException $e) {
                $this->output->writeln('  <fg=red>' . OutputFormatter::escape($e->getMessage()) . '</>');

                return $this->undone($applied, $e->getMessage());
            }

            $this->showFiles($step);

            if (!$this->consent->confirm($this->output, '  Apply this fix?', 'refactor-fix', 'apply fixes', self::UNDOES)) {
                return $this->undone($applied, 'The fix was turned down.');
            }

            try {
                $applied = [...$this->apply($step), ...$applied];
            } catch (RefusedStepException $e) {
                $this->output->writeln('  <fg=red>' . OutputFormatter::escape($e->getMessage()) . '</>');

                return $this->undone($applied, $e->getMessage());
            }

            $check = $this->suite->check();
            $wrong = $this->wrongWith($check, $step, $last, $examples);

            if ($wrong !== '') {
                $this->output->writeln($this->verdict($check, $step, $last));
                $this->output->writeln($this->indented($wrong));

                return $this->undone($applied, $wrong);
            }
        }

        $this->output->writeln($check->green()
            ? '  <fg=green>Specs still pass ✓</>'
            : '  <fg=yellow>Red, as this step meant:</> ' . implode(', ', array_map(static fn(Proposal $file): string => $file->path, $step->files)));

        return ['check' => $check, 'applied' => $applied, 'cause' => ''];
    }

    /**
     * What keeps the step from standing, a line each, or nothing when it
     * stands: where it meant to leave the suite, and with no fewer examples
     * than it found.
     */
    private function wrongWith(SuiteCheck $check, RefactorStep $step, bool $last, int $examples): string
    {
        $wrong = $this->holds($check, $step, $last) ? [] : $check->failures();

        if ($check->ran() < $examples) {
            $wrong[] = sprintf('%d examples ran where %d did: a refactoring moves or rewrites an example, never drops, skips or leaves one pending.', $check->ran(), $examples);
        }

        return implode("\n", $wrong);
    }

    private function verdict(SuiteCheck $check, RefactorStep $step, bool $last): string
    {
        return sprintf('  <fg=red>%s ✘</>', $this->holds($check, $step, $last) ? 'Examples lost' : 'Specs failed');
    }

    /**
     * Whether the suite stands where the step meant it to: green, or, for a
     * step that writes a spec first, red only in the files that step wrote.
     * The last step leaves it green.
     */
    private function holds(SuiteCheck $check, RefactorStep $step, bool $last): bool
    {
        if ($check->green()) {
            return true;
        }

        return !$last && $step->red && $check->confinedTo(array_map(static fn(Proposal $file): string => $file->path, $step->files));
    }

    /**
     * Writes every file of the step, or none: a file that cannot be written
     * undoes the ones written before it.
     *
     * @return list<Proposal> what was written, to undo in this order
     *
     * @throws RefusedStepException
     */
    private function apply(RefactorStep $step): array
    {
        $applied = [];

        foreach ($step->files as $file) {
            try {
                $this->writer->apply($file);
            } catch (RuntimeException $e) {
                array_map($this->writer->revert(...), $applied);

                throw new RefusedStepException(sprintf('%s: nothing of the step was written.', rtrim($e->getMessage(), '.')), 0, $e);
            }

            array_unshift($applied, $file);
        }

        return $applied;
    }

    /**
     * @param list<Proposal> $applied
     * @return array{check: null, applied: list<Proposal>, cause: string}
     */
    private function undone(array $applied, string $cause): array
    {
        array_map($this->writer->revert(...), $applied);

        return ['check' => null, 'applied' => [], 'cause' => $cause];
    }

    private function showFiles(RefactorStep $step): void
    {
        foreach ($step->files as $file) {
            if ($this->attended) {
                $this->output->writeln('');
            }

            $this->output->writeln(sprintf('  <fg=white>%s</>%s', $file->path, $file->isNew ? ' (new)' : ''));

            if ($this->attended) {
                $this->output->writeln(Diff::format(Diff::compute($file->isNew ? [] : explode("\n", $file->old), explode("\n", $file->new))));
            }
        }
    }

    private function indented(string $report): string
    {
        return '    ' . str_replace("\n", "\n    ", OutputFormatter::escape($report));
    }
}
