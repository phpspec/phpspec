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

namespace PhpSpec\Console\Command;

use InvalidArgumentException;
use PhpSpec\Ai\Agent\Proposal;
use PhpSpec\Ai\Agent\Writer;
use PhpSpec\Ai\Contracts\ProviderInterface;
use PhpSpec\Ai\PromptLibrary;
use PhpSpec\Ai\ProviderFactory;
use PhpSpec\Ai\RefactorJournal;
use PhpSpec\Configuration;
use PhpSpec\Console\Command\Refactor\Declined;
use PhpSpec\Console\Command\Refactor\Diff;
use PhpSpec\Console\Command\Refactor\Progress;
use PhpSpec\Console\Command\Refactor\RefactorModel;
use PhpSpec\Console\Command\Refactor\RefactorPlan;
use PhpSpec\Console\Command\Refactor\RefactorStep;
use PhpSpec\Console\Command\Refactor\RefactorTarget;
use PhpSpec\Console\Command\Refactor\RefusedStepException;
use PhpSpec\Console\Command\Refactor\SpecSuite;
use PhpSpec\Console\Command\Refactor\SubprocessSuite;
use PhpSpec\Console\Command\Refactor\SuiteCheck;
use PhpSpec\Console\Command\Refactor\TargetResolver;
use PhpSpec\Console\Command\Refactor\UnresolvedTargetException;
use PhpSpec\Console\Command\Run\Generation;
use PhpSpec\Console\Consent;
use PhpSpec\Filesystem;
use PhpSpec\RealFilesystem;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputArgument as Argument;
use Symfony\Component\Console\Input\InputInterface as Input;
use Symfony\Component\Console\Output\OutputInterface as Output;

/**
 * @internal
 * CLI command that refactors with the model, behaviour kept: it resolves a
 * target (a class, a short name, class::method, a spec file, or with no
 * target the source modified last), checks the whole suite is green, asks the
 * model for a plan of baby steps, and carries it out one step at a time, each
 * shown and asked for, the suite checked after each.
 */
final class Refactor extends Command
{
    private Filesystem $filesystem;

    /**
     * @param SpecSuite|null $suite the whole spec suite, run after each step
     * @param ProviderInterface|null $provider the model, when not the configured one
     * @param Consent|null $consent asks before the plan and each step
     */
    public function __construct(
        private readonly Configuration $config,
        ?Filesystem $filesystem = null,
        private readonly ?SpecSuite $suite = null,
        private readonly ?ProviderInterface $provider = null,
        private readonly ?Consent $consent = null,
    ) {
        $this->filesystem = $filesystem ?? new RealFilesystem();
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('refactor')
            ->setDescription('AI-powered behaviour-preserving refactoring')
            ->addArgument('target', Argument::OPTIONAL, 'Class FQCN, FQCN::method, or spec file path; the source modified last when left out');
    }

    protected function execute(Input $input, Output $output): int
    {
        $aiConfig = $this->config->getAiConfig();
        if ($aiConfig === null) {
            $output->writeln('<fg=red>' . $this->config->aiConfigProblem() . '</>');
            return 1;
        }

        if (!$this->loadBootstrap()) {
            $output->writeln('<fg=red>Bootstrap file not found.</>');
            return 1;
        }

        $root = getcwd() ?: '.';
        $argument = $input->getArgument('target');

        try {
            $target = (new TargetResolver($this->config, $this->filesystem, $root))->resolve(is_string($argument) ? $argument : null);
            $provider = $this->provider ?? ProviderFactory::create($aiConfig);
        } catch (UnresolvedTargetException|RuntimeException|InvalidArgumentException $e) {
            $output->writeln('<fg=red>' . OutputFormatter::escape($e->getMessage()) . '</>');
            return 1;
        }

        $suite = $this->suite ?? new SubprocessSuite($root);
        $output->writeln('  <fg=gray>Checking the specs...</>');
        $baseline = $suite->check();

        if (!$baseline->green()) {
            $output->writeln('');
            $output->writeln('<fg=red>Specs must pass before refactoring.</> Fix failing specs first.');
            $output->writeln('');
            $output->writeln(OutputFormatter::escape($baseline->report()));
            return 1;
        }

        $model = new RefactorModel($provider, $this->config, $this->filesystem, $root, new PromptLibrary($this->filesystem));
        $journal = new RefactorJournal($this->filesystem);
        $progress = new Progress($output);

        $output->writeln('');
        $plan = $progress->during('Planning the refactoring', fn(): RefactorPlan|Declined => $model->plan($target, $journal->rendered()));

        if ($plan instanceof Declined) {
            $output->writeln('');
            $output->writeln(sprintf('  <fg=yellow>Nothing to refactor in %s: %s</>', OutputFormatter::escape($target->fqcn), OutputFormatter::escape($plan->reason)));
            return 0;
        }

        $this->showPlan($output, $plan, $progress);
        $consent = $this->consent ?? new Consent('Run phpspec refactor in a terminal to %s.', $input->isInteractive() ? Generation::Asks : Generation::Declines);

        if (!$consent->confirm($output, '  Would you like to proceed?', 'refactor-plan', 'carry out the plan')) {
            if ($input->isInteractive()) {
                $output->writeln('  Left unchanged.');
            }

            return 0;
        }

        $kept = [];
        $exitCode = $this->carryOut($output, $plan, $model, $suite, $consent, $progress, new Writer($this->filesystem, $root), $kept);

        if ($kept !== []) {
            $journal->record($this->relativeClass($target), $plan->technique, implode('; ', $kept));
        }

        return $exitCode;
    }

    /**
     * Takes the plan one step at a time: the model writes it, the developer
     * sees it and says yes, it is written and the suite checked. A step that
     * breaks more than it meant to gets one fix; still broken, it is undone
     * and the run stops there, the steps before it kept.
     *
     * @param list<string> $kept the titles of the steps kept, filled in as they are
     */
    private function carryOut(Output $output, RefactorPlan $plan, RefactorModel $model, SpecSuite $suite, Consent $consent, Progress $progress, Writer $writer, array &$kept): int
    {
        foreach ($plan->steps as $index => $planned) {
            $number = $index + 1;
            $last = $number === count($plan->steps);

            try {
                $output->writeln('');
                $step = $progress->during($planned->doing, fn(): RefactorStep => $model->step($plan, $index));
                $progress->checklist($plan, $index);
                $this->showFiles($output, $step);

                if (!$consent->confirm($output, '  Apply this step?', 'refactor-step', 'apply the remaining refactoring steps')) {
                    $output->writeln(sprintf('  Stopped before step %d: the steps before it are kept.', $number));

                    return 0;
                }

                $applied = $this->apply($writer, $step);
                $check = $suite->check();

                if (!$this->holds($check, $step, $last)) {
                    $output->writeln('  <fg=red>Specs failed ✘</>, asking for a fix...');
                    $output->writeln($this->indented($check->report()));
                    $output->writeln('');
                    $step = $progress->during('Fixing it', fn(): RefactorStep => $model->fix($step, $check->report()));
                    $this->showFiles($output, $step);
                    $fixed = $consent->confirm($output, '  Apply this fix?', 'refactor-fix', 'apply fixes');
                    $applied = $fixed ? [...$this->apply($writer, $step), ...$applied] : $applied;
                    $check = $fixed ? $suite->check() : $check;

                    if (!$fixed || !$this->holds($check, $step, $last)) {
                        array_map($writer->revert(...), $applied);
                        $output->writeln($fixed ? '  <fg=red>Specs failed ✘</>' : '');
                        $output->writeln($fixed ? $this->indented($check->report()) : '');
                        $output->writeln(sprintf('  Stopped at step %d: the steps before it are kept, this one is undone.', $number));

                        return 1;
                    }
                }
            } catch (RefusedStepException $e) {
                $output->writeln('  <fg=red>' . OutputFormatter::escape($e->getMessage()) . '</>');
                $output->writeln(sprintf('  Stopped at step %d: the steps before it are kept.', $number));

                return 1;
            }

            $output->writeln($check->green()
                ? '  <fg=green>Specs still pass ✓</>'
                : '  <fg=yellow>Red, as this step meant:</> ' . implode(', ', array_map(static fn(Proposal $file): string => $file->path, $step->files)));
            $kept[] = $planned->title;
        }

        $output->writeln('');
        $output->writeln(sprintf('  <fg=green>Refactoring done: %s ✓</>', OutputFormatter::escape($plan->technique)));

        return 0;
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
    private function apply(Writer $writer, RefactorStep $step): array
    {
        foreach ($step->files as $file) {
            $writer->apply($file);
        }

        return array_reverse($step->files);
    }

    private function showPlan(Output $output, RefactorPlan $plan, Progress $progress): void
    {
        $output->writeln('');
        $output->writeln('<options=bold>' . OutputFormatter::escape($plan->technique) . '</>');
        $output->writeln('');
        $output->writeln(OutputFormatter::escape($this->wrapped($plan->rationale)));
        $output->writeln('');
        $output->writeln('Plan:');
        $output->writeln('');
        $progress->checklist($plan);
        $output->writeln('');
        $output->writeln('PhpSpec has written a refactoring plan and is ready to start.');
    }

    private function showFiles(Output $output, RefactorStep $step): void
    {
        foreach ($step->files as $file) {
            $output->writeln('');
            $output->writeln(sprintf('  <fg=white>%s</>%s', $file->path, $file->isNew ? ' (new)' : ''));
            $output->writeln(Diff::format(Diff::compute($file->isNew ? [] : explode("\n", $file->old), explode("\n", $file->new))));
        }
    }

    /**
     * The model's prose, each line wrapped at 76 columns, a list item's
     * continuation lined up under its text.
     */
    private function wrapped(string $prose): string
    {
        $lines = [];

        foreach (explode("\n", trim($prose)) as $line) {
            $indent = strlen($line) - strlen(ltrim($line)) + (str_starts_with(ltrim($line), '- ') ? 2 : 0);
            $lines[] = wordwrap($line, 76, "\n" . str_repeat(' ', $indent));
        }

        return implode("\n", $lines);
    }

    private function indented(string $report): string
    {
        return '    ' . str_replace("\n", "\n    ", OutputFormatter::escape($report));
    }

    /**
     * The class-ish name the journal keeps a target under: its source path
     * under the source directory (src/App/X.php reads as App\X).
     */
    private function relativeClass(RefactorTarget $target): string
    {
        $relative = ltrim(substr($target->sourceFile, strlen(getcwd() ?: '.')), '/\\');
        $srcDir = ltrim($this->config->getSrcPath(), './');
        if (str_starts_with($relative, $srcDir . '/')) {
            $relative = substr($relative, strlen($srcDir) + 1);
        }

        return str_replace('/', '\\', preg_replace('/\.php$/', '', $relative) ?? $relative);
    }

    private function loadBootstrap(): bool
    {
        $bootstrap = $this->config->getBootstrap() ?? (file_exists('vendor/autoload.php') ? 'vendor/autoload.php' : null);
        if ($bootstrap === null) {
            return true;
        }
        if (!file_exists($bootstrap)) {
            return false;
        }
        require_once $bootstrap;
        return true;
    }
}
