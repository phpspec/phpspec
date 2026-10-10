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
 * and the whole suite checked. A step that breaks more than it meant to gets
 * one fix; still broken, or not fixed at all, it is undone and the run stops
 * there, the steps before it kept.
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
     * @param list<string> $kept the titles of the steps kept, filled in as they are
     */
    public function carryOut(RefactorPlan $plan, array &$kept): int
    {
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

            if (!$this->stands($step, $number === count($plan->steps))) {
                $this->output->writeln(sprintf('  Stopped at step %d: the steps before it are kept, this one is undone.', $number));

                return 1;
            }

            $kept[] = $planned->title;
        }

        $this->output->writeln('');
        $this->output->writeln(sprintf('  <fg=green>Refactoring done: %s ✓</>', OutputFormatter::escape($plan->technique)));

        return 0;
    }

    /**
     * Writes the step and checks the suite, fixing it once when it broke more
     * than it meant to. Whether it stands; a step that does not is undone.
     */
    private function stands(RefactorStep $step, bool $last): bool
    {
        $applied = $this->apply($step);
        $check = $this->suite->check();

        if (!$this->holds($check, $step, $last)) {
            $this->output->writeln('  <fg=red>Specs failed ✘</>, asking for a fix...');
            $this->output->writeln($this->indented($check->report()));
            $this->output->writeln('');

            try {
                $step = $this->progress->during('Fixing it', fn(): RefactorStep => $this->model->fix($step, $check->report()));
            } catch (RefusedStepException|ModelFailedException $e) {
                return $this->undone($applied, '  <fg=red>' . OutputFormatter::escape($e->getMessage()) . '</>');
            }

            $this->showFiles($step);

            if (!$this->consent->confirm($this->output, '  Apply this fix?', 'refactor-fix', 'apply fixes')) {
                return $this->undone($applied, '');
            }

            $applied = [...$this->apply($step), ...$applied];
            $check = $this->suite->check();

            if (!$this->holds($check, $step, $last)) {
                return $this->undone($applied, "  <fg=red>Specs failed ✘</>\n" . $this->indented($check->report()));
            }
        }

        $this->output->writeln($check->green()
            ? '  <fg=green>Specs still pass ✓</>'
            : '  <fg=yellow>Red, as this step meant:</> ' . implode(', ', array_map(static fn(Proposal $file): string => $file->path, $step->files)));

        return true;
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
    private function undone(array $applied, string $said): bool
    {
        array_map($this->writer->revert(...), $applied);

        if ($said !== '') {
            $this->output->writeln($said);
        }

        return false;
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
