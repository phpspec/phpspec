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
use PhpSpec\Ai\Agent\Writer;
use PhpSpec\Ai\Contracts\ProviderInterface;
use PhpSpec\Ai\PromptLibrary;
use PhpSpec\Ai\ProviderFactory;
use PhpSpec\Ai\RefactorJournal;
use PhpSpec\Configuration;
use PhpSpec\Console\Command\Refactor\Declined;
use PhpSpec\Console\Command\Refactor\PlannedStep;
use PhpSpec\Console\Command\Refactor\Progress;
use PhpSpec\Console\Command\Refactor\RefactorModel;
use PhpSpec\Console\Command\Refactor\RefactorPlan;
use PhpSpec\Console\Command\Refactor\RefactorTarget;
use PhpSpec\Console\Command\Refactor\SpecSuite;
use PhpSpec\Console\Command\Refactor\StepByStep;
use PhpSpec\Console\Command\Refactor\SubprocessSuite;
use PhpSpec\Console\Command\Refactor\SuiteCheck;
use PhpSpec\Console\Command\Refactor\TargetResolver;
use PhpSpec\Console\Command\Refactor\UnresolvedTargetException;
use PhpSpec\Console\Command\Run\Generation;
use PhpSpec\Console\Consent;
use PhpSpec\Console\ReceiptFormat;
use PhpSpec\Filesystem;
use PhpSpec\Offers\Offer;
use PhpSpec\Offers\OfferBook;
use PhpSpec\RealFilesystem;
use PhpSpec\Report\Formatter\Agent\Schema;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputArgument as Argument;
use Symfony\Component\Console\Input\InputInterface as Input;
use Symfony\Component\Console\Input\InputOption as Option;
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
            ->addArgument('target', Argument::OPTIONAL, 'A class (App/Till), a short name (Till), Class::method, a source file or a spec file; the source modified last when left out')
            ->addOption('format', 'f', Option::VALUE_REQUIRED, 'Output format: pretty, or agent (machine-readable JSON receipt for coding agents)', 'pretty');
    }

    protected function execute(Input $input, Output $output): int
    {
        $forAgent = ReceiptFormat::of($input) === ReceiptFormat::Agent;
        $aiConfig = $this->config->getAiConfig();

        if ($aiConfig === null) {
            return $this->refuse($output, $forAgent, (string) $this->config->aiConfigProblem());
        }

        $root = getcwd() ?: '.';
        $argument = $input->getArgument('target');

        try {
            $target = (new TargetResolver($this->config, $this->filesystem, $root))->resolve(is_string($argument) ? $argument : null);
            $provider = $this->provider ?? ProviderFactory::create($aiConfig);
        } catch (UnresolvedTargetException $e) {
            return $this->refuse($output, $forAgent, $e->problem(), $e->remedy());
        } catch (RuntimeException|InvalidArgumentException $e) {
            return $this->refuse($output, $forAgent, $e->getMessage());
        }

        $suite = $this->suite ?? new SubprocessSuite($root, $this->config);
        if (!$forAgent) {
            $output->writeln('  <fg=gray>Checking the specs...</>');
        }
        $baseline = $suite->check();

        if (!$baseline->green()) {
            return $this->refuse($output, $forAgent, 'Specs must pass before refactoring.', 'Fix what the run left first.', $baseline->failures());
        }

        $model = new RefactorModel($provider, $this->config, $this->filesystem, $root, new PromptLibrary($this->filesystem));
        $journal = new RefactorJournal($this->filesystem);

        try {
            return $forAgent
                ? $this->planForAgent($output, $target, $model->plan($target, $journal->rendered()))
                : $this->refactorStepByStep($input, $output, $target, $model, $suite, $baseline, $journal);
        } catch (RuntimeException $e) {
            return $this->refuse($output, $forAgent, $e->getMessage());
        }
    }

    /**
     * Asks for the plan and carries it out step by step. Somebody attending
     * the run sees the plan and is asked to go ahead, and then before each
     * step; with --no-interaction nobody is asked and the plan goes unshown.
     */
    private function refactorStepByStep(Input $input, Output $output, RefactorTarget $target, RefactorModel $model, SpecSuite $suite, SuiteCheck $baseline, RefactorJournal $journal): int
    {
        $attended = $input->isInteractive();
        $progress = new Progress($output);

        $output->writeln('');
        $plan = $progress->during('Planning the refactoring', fn(): RefactorPlan|Declined => $model->plan($target, $journal->rendered()));

        if ($plan instanceof Declined) {
            $output->writeln('');
            $output->writeln(sprintf('<fg=yellow>%s</>', OutputFormatter::escape('  ' . wordwrap(sprintf('Nothing to refactor in %s: %s', $target->fqcn, $plan->reason), 76, "\n  "))));

            return 0;
        }

        $consent = $attended ? $this->consent ?? new Consent('Run with --no-interaction to %s.') : new Consent('', Generation::Accepts);

        if ($attended) {
            $this->showPlan($output, $plan, $progress);

            $decided = $consent->decide($output, '  Would you like to proceed?', 'refactor-plan', 'carry out the plan');

            if ($decided === null) {
                $consent->nobody($output, 'carry out the plan');

                return 0;
            }

            if (!$decided) {
                $output->writeln('  Left unchanged.');

                return 0;
            }
        }

        $steps = new StepByStep($output, $model, $suite, $consent, $progress, new Writer($this->filesystem, getcwd() ?: '.'), $journal, $attended);

        return $steps->carryOut($plan, $baseline, $target->fqcn)->exitCode;
    }

    /**
     * The plan, or the model's reason to decline, as one receipt. A coding
     * agent cannot answer the questions the plan is carried out by, so the
     * plan is offered once, whole: accepting it carries it out unattended.
     */
    private function planForAgent(Output $output, RefactorTarget $target, RefactorPlan|Declined $plan): int
    {
        $about = ['class' => $target->fqcn]
            + ($target->method === null ? [] : ['method' => $target->method])
            + ['source' => $this->relative($target->sourceFile), 'spec' => $this->relative($target->specFile)];

        if ($plan instanceof Declined) {
            $this->receipt($output, $about + ['declined' => $plan->reason]);

            return 0;
        }

        $offer = Offer::refactor($target->fqcn . ($target->method === null ? '' : '::' . $target->method), $plan->toArray(), $plan->read);
        (new OfferBook($this->filesystem))->record($offer);

        $this->receipt($output, $about + [
            'technique' => $plan->technique,
            'rationale' => $plan->rationale,
            'steps' => array_map(static fn(PlannedStep $step): string => $step->title, $plan->steps),
            'offer' => ['id' => $offer->id, 'action' => $offer->action, 'target' => $offer->target],
        ]);

        return 0;
    }

    /**
     * Says why there will be no refactoring and what to do about it, as a
     * receipt for a coding agent, else in red.
     *
     * @param list<string> $failures the failing specs, one per line
     */
    private function refuse(Output $output, bool $forAgent, string $problem, string $remedy = '', array $failures = []): int
    {
        if ($forAgent) {
            $this->receipt($output, ['error' => $problem] + ($remedy === '' ? [] : ['remedy' => $remedy]) + ($failures === [] ? [] : ['failures' => $failures]));

            return 1;
        }

        if ($failures !== []) {
            $output->writeln('');
        }

        $output->writeln(rtrim(sprintf('<fg=red>%s</> %s', OutputFormatter::escape($problem), OutputFormatter::escape($remedy))));

        if ($failures !== []) {
            $output->writeln('');
            $output->writeln(OutputFormatter::escape(implode("\n", $failures)));
        }

        return 1;
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function receipt(Output $output, array $fields): void
    {
        $json = json_encode(['v' => Schema::V, 'action' => 'refactor'] + $fields, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
        $output->write($json . "\n", false, Output::OUTPUT_RAW);
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

    private function relative(string $file): string
    {
        return ltrim(substr($file, strlen(getcwd() ?: '.')), '/\\');
    }
}
