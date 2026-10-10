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
use PhpSpec\Console\Consent;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Output\OutputInterface as Output;

/**
 * @internal
 * Carries out a refactoring plan with the developer, one step at a time: the
 * model writes the step, the developer sees it and says yes, it is written
 * and the whole suite checked. A step that breaks more than it meant to, or
 * leaves fewer examples than it found, gets one fix; still wrong, or not
 * fixed at all, it is undone and the run stops there, the steps before it
 * kept.
 */
final readonly class StepByStep
{
    public function __construct(
        private Output $output,
        private RefactorModel $model,
        private SpecSuite $suite,
        private Consent $consent,
        private Progress $progress,
        private Writer $writer,
    ) {}

    /**
     * @param SuiteCheck $baseline the suite before the first step
     * @param list<string> $kept the titles of the steps kept, filled in as they are
     */
    public function carryOut(RefactorPlan $plan, SuiteCheck $baseline, array &$kept): int
    {
        $examples = $baseline->examples();

        foreach ($plan->steps as $index => $planned) {
            $number = $index + 1;
            $this->output->writeln('');

            try {
                $step = $this->progress->during($planned->doing, fn(): RefactorStep => $this->model->step($plan, $index));
            } catch (RefusedStepException|ModelFailedException $e) {
                $this->output->writeln('  <fg=red>' . OutputFormatter::escape($e->getMessage()) . '</>');
                $this->output->writeln(sprintf('  Stopped at step %d: the steps before it are kept.', $number));

                return 1;
            }

            $this->progress->checklist($plan, $index);
            $this->showFiles($step);

            if (!$this->consent->confirm($this->output, '  Apply this step?', 'refactor-step', 'apply the remaining refactoring steps')) {
                $this->output->writeln(sprintf('  Stopped before step %d: the steps before it are kept.', $number));

                return 0;
            }

            $standing = $this->standing($step, $number === count($plan->steps), $examples);

            if ($standing === null) {
                $this->output->writeln(sprintf('  Stopped at step %d: the steps before it are kept, this one is undone.', $number));

                return 1;
            }

            $examples = $standing->examples();
            $kept[] = $planned->title;
        }

        $this->output->writeln('');
        $this->output->writeln(sprintf('  <fg=green>Refactoring done: %s ✓</>', OutputFormatter::escape($plan->technique)));

        return 0;
    }

    /**
     * Writes the step and checks the suite, fixing it once when it does not
     * stand. The suite it stands on, or nothing when it was undone.
     *
     * @param int $examples how many examples there were before the step
     */
    private function standing(RefactorStep $step, bool $last, int $examples): ?SuiteCheck
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
                return $this->undone($applied, '  <fg=red>' . OutputFormatter::escape($e->getMessage()) . '</>');
            }

            $this->showFiles($step);

            if (!$this->consent->confirm($this->output, '  Apply this fix?', 'refactor-fix', 'apply fixes')) {
                return $this->undone($applied, '');
            }

            $applied = [...$this->apply($step), ...$applied];
            $check = $this->suite->check();
            $wrong = $this->wrongWith($check, $step, $last, $examples);

            if ($wrong !== '') {
                return $this->undone($applied, $this->verdict($check, $step, $last) . "\n" . $this->indented($wrong));
            }
        }

        $this->output->writeln($check->green()
            ? '  <fg=green>Specs still pass ✓</>'
            : '  <fg=yellow>Red, as this step meant:</> ' . implode(', ', array_map(static fn(Proposal $file): string => $file->path, $step->files)));

        return $check;
    }

    /**
     * What keeps the step from standing, a line each, or nothing when it
     * stands: where it meant to leave the suite, and with no fewer examples
     * than it found.
     */
    private function wrongWith(SuiteCheck $check, RefactorStep $step, bool $last, int $examples): string
    {
        $wrong = $this->holds($check, $step, $last) ? [] : $check->failures();

        if ($check->examples() < $examples) {
            $wrong[] = sprintf('The specs have %d examples where they had %d: a refactoring moves or rewrites an example, never drops one.', $check->examples(), $examples);
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
     * @return list<Proposal> what was written, to undo in this order
     */
    private function apply(RefactorStep $step): array
    {
        foreach ($step->files as $file) {
            $this->writer->apply($file);
        }

        return array_reverse($step->files);
    }

    /**
     * @param list<Proposal> $applied
     */
    private function undone(array $applied, string $said): null
    {
        array_map($this->writer->revert(...), $applied);

        if ($said !== '') {
            $this->output->writeln($said);
        }

        return null;
    }

    private function showFiles(RefactorStep $step): void
    {
        foreach ($step->files as $file) {
            $this->output->writeln('');
            $this->output->writeln(sprintf('  <fg=white>%s</>%s', $file->path, $file->isNew ? ' (new)' : ''));
            $this->output->writeln(Diff::format(Diff::compute($file->isNew ? [] : explode("\n", $file->old), explode("\n", $file->new))));
        }
    }

    private function indented(string $report): string
    {
        return '    ' . str_replace("\n", "\n    ", OutputFormatter::escape($report));
    }
}
