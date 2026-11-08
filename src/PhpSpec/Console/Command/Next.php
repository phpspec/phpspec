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

use PhpSpec\Ai\Agent\Agent;
use PhpSpec\Ai\Agent\Grounding;
use PhpSpec\Ai\Agent\Outcome;
use PhpSpec\Ai\Agent\OutcomePresenter;
use PhpSpec\Ai\Agent\Phase;
use PhpSpec\Ai\Agent\ProjectPath;
use PhpSpec\Ai\Agent\Request;
use PhpSpec\Ai\Agent\Step;
use PhpSpec\Ai\Contracts\ProviderInterface;
use PhpSpec\Ai\ModelFailedException;
use PhpSpec\Ai\RefactorJournal;
use PhpSpec\CodeGeneration\StepGenerator;
use PhpSpec\CodeGeneration\StepsHome;
use PhpSpec\CommandLine;
use PhpSpec\Configuration;
use PhpSpec\Console\Command\Pair\SpecRunner;
use PhpSpec\Console\Command\Pair\SubprocessRunner;
use PhpSpec\Console\Command\Run\RecencyScanner;
use PhpSpec\Console\Command\Run\SuiteSummary;
use PhpSpec\Console\Consent;
use PhpSpec\Console\Prompt;
use PhpSpec\Console\ReceiptFormat;
use PhpSpec\Filesystem;
use PhpSpec\RealFilesystem;
use PhpSpec\Report\Formatter\Agent\Schema;
use PhpSpec\StoryBDD\StepVocabulary;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface as Input;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\StreamableInputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface as Output;

/**
 * @internal
 * CLI command that uses AI to suggest what to describe (spec or feature) next.
 *
 * Scans the project's source, spec, and feature files, sends the project
 * context to the LLM, and displays a suggestion for the next BDD step.
 * Offers to create the suggested artifact.
 */
final class Next extends Command
{
    private Filesystem $filesystem;

    private readonly SpecRunner $specRunner;

    private readonly CommandLine $commandLine;

    /** @var (callable(array{provider: string, model?: string, api_key?: string}, string): array{type: string, target: string, reason: string})|null */
    private $suggestFn;

    /**
     * @param Configuration $config
     * @param Filesystem|null $filesystem
     * @param (callable(array{provider: string, model?: string, api_key?: string}, string): array{type: string, target: string, reason: string})|null $suggestFn injectable display seam; receives the AI config and the rendered situation
     * @param SpecRunner|null $specRunner runs the suite for feature grounding; defaults to a subprocess
     * @param ProviderInterface|null $provider injectable AI seam for the agent pipeline
     * @param CommandLine|null $commandLine how a suggested command is typed; the one this process was started by when not given
     */
    public function __construct(
        private readonly Configuration $config,
        ?Filesystem $filesystem = null,
        ?callable $suggestFn = null,
        ?SpecRunner $specRunner = null,
        private readonly ?ProviderInterface $provider = null,
        private readonly ?Prompt $prompt = null,
        ?CommandLine $commandLine = null,
    ) {
        $this->filesystem = $filesystem ?? new RealFilesystem();
        $this->commandLine = $commandLine ?? CommandLine::invoked($config);
        $this->suggestFn = $suggestFn;
        $this->specRunner = $specRunner ?? new SubprocessRunner($config);
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('next')
            ->setDescription('AI suggests what to describe or specify next')
            ->addOption('format', 'f', InputOption::VALUE_REQUIRED, 'Output format: pretty, or agent (machine-readable JSON for coding agents)', 'pretty');
    }

    protected function execute(Input $input, Output $output): int
    {
        $forAgent = ReceiptFormat::of($input) === ReceiptFormat::Agent;

        // 1. Check AI config
        $aiConfig = $this->config->getAiConfig();
        if ($aiConfig === null) {
            $refusal = $this->config->aiConfigRefusal() ?? ['problem' => '', 'remedy' => null];

            return $forAgent
                ? $this->refuse($output, true, $refusal['problem'], $refusal['remedy'])
                : $this->refuse($output, false, (string) $this->config->aiConfigProblem());
        }

        // 2. Load bootstrap
        $missingBootstrap = $this->loadBootstrap();
        if ($missingBootstrap !== null) {
            return $this->refuse($output, $forAgent, sprintf('Bootstrap file not found: %s', $missingBootstrap));
        }

        // 3. Scan project and get suggestion
        if (!$forAgent) {
            $output->writeln('');
            $output->writeln('  <fg=gray>Analysing project...</>');
            $output->writeln('');
        }

        try {
            $suggestion = $this->getSuggestion($aiConfig);
        } catch (ModelFailedException $e) {
            return $this->refuse($output, $forAgent, $e->getMessage());
        }

        // 4. Validate — if we got no target and no reason, the response was unusable
        if ($suggestion['target'] === '' && $suggestion['reason'] === '') {
            if ($forAgent) {
                $output->write((new OutcomePresenter())->render('next', new Outcome(null, [], 'Could not get a suggestion. Please try again.')), false, Output::OUTPUT_RAW);

                return 1;
            }
            $output->writeln('  <fg=yellow>Could not get a suggestion. Please try again.</>');
            $output->writeln('');
            return 1;
        }

        // 4b. If the suggested spec already exists, never re-describe it (that
        // just loops) — coach to run so the missing class is driven out instead.
        if ($suggestion['type'] === 'spec' && $this->specFileExists($suggestion['target'])) {
            $suggestion = [
                'type' => 'info',
                'target' => '',
                'reason' => sprintf('A spec for "%s" already exists. Run it to drive out the class.', $suggestion['target']),
            ];
        }

        // 4c. Red, green, REFACTOR happens once: a refactor suggestion for a
        // target unchanged since the last recorded refactoring steers to
        // growth instead of polishing the same class forever.
        $suggestion = $this->steerAwayFromStaleRefactor($suggestion);

        $actuation = $this->actuationFor($suggestion);

        // The agent document carries the suggestion and the exact command that
        // acts on it; a machine consumer is never prompted.
        if ($forAgent) {
            $run = $actuation !== null ? $this->commandLine->for(...$actuation['words']) : null;
            $outcome = new Outcome(null, [], '', $suggestion);
            $output->write((new OutcomePresenter())->render('next', $outcome, [], $run), false, Output::OUTPUT_RAW);

            return 0;
        }

        // 5. Display
        $this->displaySuggestion($output, $suggestion);

        // 6. Offer to act (only when the suggestion determined an actuation)
        if ($actuation === null) {
            if ($suggestion['type'] === 'implement' && $suggestion['target'] !== '') {
                $output->writeln('  <fg=gray>Run:</> ' . $this->commandLine->for('run'));
            }

            return 0;
        }

        return $this->offerToRun($input, $output, $this->commandLine->for(...$actuation['words']), $actuation['command'], $actuation['args'], $actuation['ask']);
    }

    /**
     * The command a suggestion actuates through, with its bound arguments and
     * the words of its command line, shared by the interactive offer and the agent document so
     * both always name the same invocation. Null when nothing actuates: an
     * info suggestion, or a failing subject that is no class (its honest next
     * move is a run, which shows the failure).
     *
     * @param array{type: string, target: string, reason: string} $suggestion
     * @return array{command: string, args: array<string, mixed>, words: list<string>, ask: string}|null
     */
    private function actuationFor(array $suggestion): ?array
    {
        $target = $suggestion['target'];
        if ($target === '') {
            return null;
        }

        $create = 'Would you like me to create that for you?';
        $generate = fn(string $instruction): array => [
            'command' => 'generate',
            'args' => ['instruction' => [$instruction]],
            'words' => ['generate', $instruction],
            'ask' => $create,
        ];

        return match ($suggestion['type']) {
            'spec' => [
                'command' => 'describe',
                'args' => ['class' => str_replace('\\', '/', $target)],
                'words' => ['describe', str_replace('\\', '/', $target)],
                'ask' => $create,
            ],
            'feature' => $generate('a feature for ' . $target),
            'steps' => $generate('the steps for ' . $target),
            'example' => $generate($this->taskInstruction('add a spec example for ' . $target, $suggestion['reason'])),
            'implement' => preg_match('~^[A-Z][A-Za-z0-9]*(?:\\\\[A-Z][A-Za-z0-9]*)*$~', $target) === 1
                ? $generate($this->taskInstruction('implement ' . $target, $suggestion['reason']))
                : null,
            'refactor' => [
                'command' => 'refactor',
                'args' => ['target' => str_replace('\\', '/', $target)],
                'words' => ['refactor', str_replace('\\', '/', $target)],
                'ask' => 'Would you like me to run it now?',
            ],
            default => null,
        };
    }

    /**
     * Whether a spec file already exists for the given class, so `next` never
     * suggests describing a spec that is already there.
     */
    private function specFileExists(string $fqcn): bool
    {
        $specPath = $this->config->specDir();
        $file = getcwd() . DIRECTORY_SEPARATOR . $specPath . DIRECTORY_SEPARATOR
            . str_replace('\\', DIRECTORY_SEPARATOR, $fqcn) . $this->config->getSpecSuffix();

        return $this->filesystem->exists($file);
    }

    /**
     * The suggestion for what to build next, through the agent pipeline: the
     * suite grounding is seeded from a real run, the current TDD step resolves
     * deterministically, and the model answers with a suggest_next tool call
     * (never JSON-in-prose to parse).
     *
     * @param array{provider: string, model?: string, api_key?: string} $aiConfig
     * @return array{type: string, target: string, reason: string}
     *
     * @throws ModelFailedException
     */
    private function getSuggestion(array $aiConfig): array
    {
        // Feature (story) state grounds the suggestion so `next` can favour
        // features when they are present; it is null for a spec-only project.
        $grounding = $this->featureGrounding();

        if ($this->suggestFn !== null) {
            $situation = $grounding === null ? '' : Request::suiteText($grounding);

            return ($this->suggestFn)($aiConfig, $situation);
        }

        // The grounded cases need no imagination: when the suite state
        // determines the step and its subject, the suggestion IS the step.
        $suggestion = self::suggestionFromStep(Step::resolve('', $grounding ?? Grounding::empty()));
        if ($suggestion !== null) {
            return $suggestion;
        }

        $agent = new Agent($this->config, $this->filesystem, $this->provider);
        $outcome = $agent->chat('next', '', $grounding);

        if ($outcome->failure !== null) {
            throw new ModelFailedException(sprintf('The model did not answer: %s', $outcome->failure));
        }

        $reason = (string) ($outcome->data['reason'] ?? '');

        return [
            'type' => (string) ($outcome->data['type'] ?? 'info'),
            'target' => (string) ($outcome->data['target'] ?? ''),
            'reason' => $reason !== '' ? $reason : $outcome->prose,
        ];
    }

    /**
     * @param array{type: string, target: string, reason: string} $suggestion
     */
    private function displaySuggestion(Output $output, array $suggestion): void
    {
        $typeLabel = match ($suggestion['type']) {
            'spec' => 'Describe a spec for',
            'feature' => 'Write a feature scenario for',
            'steps' => 'Write the steps for',
            'implement' => 'Implement',
            'example' => 'Add an example to',
            'refactor' => 'Refactor',
            default => '',
        };

        if ($typeLabel !== '' && $suggestion['target'] !== '') {
            $output->writeln("  <options=bold>$typeLabel</> <fg=bright-blue;options=bold>{$suggestion['target']}</>");
        }

        if ($suggestion['reason'] !== '') {
            $output->writeln('');
            foreach (explode("\n", wordwrap($suggestion['reason'], 76)) as $line) {
                $output->writeln("  <fg=gray>$line</>");
            }
        }

        $output->writeln('');
    }

    /**
     * Offers to act on the suggestion: a [Y/n] confirmation and the command
     * run on a yes, the command to run printed when nobody is there to ask.
     *
     * @param array<string, mixed> $args the bound arguments for the command
     */
    private function offerToRun(Input $input, Output $output, string $hint, string $command, array $args, string $ask = 'Would you like me to create that for you?'): int
    {
        $consent = new Consent('', prompt: $this->prompt ?? new Prompt($input instanceof StreamableInputInterface ? $input->getStream() : null));
        $decided = $input->isInteractive() ? $consent->decide($output, "  <fg=yellow>$ask</>", 'next', 'act on suggestions') : null;

        if ($decided === null) {
            $output->writeln('  <fg=gray>Run:</> ' . $hint);

            return 0;
        }

        if (!$decided) {
            return 0;
        }

        $application = $this->getApplication();
        if ($application === null) {
            return 1;
        }

        return $application->find($command)->run(new ArrayInput($args), $output);
    }

    /**
     * The generate instruction for an offer: the task, then the suggestion's
     * reason so the one-shot makes the change the suggestion meant. File
     * tokens are stripped from the reason, because a path in it would reroute
     * the instruction to that file's artifact.
     */
    private function taskInstruction(string $task, string $reason): string
    {
        $hint = trim((string) preg_replace(['~[A-Za-z0-9_./\\\\-]+\.(?:feature|php)~', '~\s+~'], ['', ' '], $reason));

        return $task . ($hint !== '' ? ': ' . $hint : '');
    }

    /**
     * Replaces a refactor suggestion with a growth steer when the journal
     * shows its target already polished and unchanged since: red, green,
     * REFACTOR happens once per change. A backstop; the model is told the
     * same journal up front, so it normally proposes growth itself.
     *
     * @param array{type: string, target: string, reason: string} $suggestion
     * @return array{type: string, target: string, reason: string}
     */
    private function steerAwayFromStaleRefactor(array $suggestion): array
    {
        if ($suggestion['type'] !== 'refactor' || $suggestion['target'] === '') {
            return $suggestion;
        }

        $polished = (new RefactorJournal($this->filesystem))->unchangedTargets(getcwd() ?: '.', $this->config->getSourceLayout());
        if (!in_array($suggestion['target'], $polished, true)) {
            return $suggestion;
        }

        return [
            'type' => 'info',
            'target' => '',
            'reason' => sprintf('"%s" was already refactored and has not changed since. Grow the story instead: add the next scenario or feature.', $suggestion['target']),
        ];
    }

    /**
     * The suggestion a resolved step determines on its own: undefined steps to
     * write, a failing example to make green, or a pending gap to fill need no
     * imagination, so no model call and no latency. Null for the open-ended
     * states (empty project, everything green) where the model's suggestion is
     * the value.
     *
     * @return array{type: string, target: string, reason: string}|null
     */
    private static function suggestionFromStep(?Step $step): ?array
    {
        if ($step === null || $step->subject === null) {
            return null;
        }

        return match ($step->phase) {
            Phase::WriteSteps => ['type' => 'steps', 'target' => $step->subject, 'reason' => ucfirst($step->because) . '. Write the steps.'],
            Phase::WriteCode => ['type' => 'implement', 'target' => $step->subject, 'reason' => ucfirst($step->because) . '. Make it green.'],
            Phase::WriteSpec => ['type' => 'example', 'target' => $step->subject, 'reason' => ucfirst($step->because) . '.'],
            default => null,
        };
    }

    /**
     * The live feature (story) grounding for the suggestion: a run of the whole
     * suite (--all) plus the last-touched feature and source. Null for a
     * spec-only project (no features dir, or a run that executed no feature),
     * so `next` keeps its spec-only behaviour then.
     */
    private function featureGrounding(): ?Grounding
    {
        $featuresDir = getcwd() . '/' . trim($this->config->getFeaturesPath(), './');
        if (!$this->filesystem->exists($featuresDir) || !$this->filesystem->isDir($featuresDir)) {
            return null;
        }

        $summary = $this->specRunner->run('--all', new BufferedOutput())?->summary;
        if ($summary === null || !$summary->hasFeatures()) {
            return null;
        }

        $recency = new RecencyScanner($this->filesystem);

        return new Grounding(
            $summary,
            ProjectPath::relativeOrNull($recency->mostRecentFeature($featuresDir)),
            ProjectPath::relativeOrNull($recency->mostRecentSource(...$this->sourceDirectories(getcwd() ?: '.'))),
            namedFiles: $this->workingStoryFiles($summary, $recency),
        );
    }

    /**
     * The files of the story being worked on, for the model to actually read:
     * a red feature with no failing example means judgement is needed (is the
     * behaviour specified and the code lagging, or the other way round?), and
     * that judgement is impossible from file names alone. Empty when no
     * feature is red, keeping the leaner context everywhere else.
     *
     * @return array<string, string> relative path => contents
     */
    private function workingStoryFiles(SuiteSummary $summary, RecencyScanner $recency): array
    {
        $red = $summary->redFeature();
        if ($red === null) {
            return [];
        }

        $cwd = getcwd() ?: '.';
        $feature = ProjectPath::normalize($red['path']);
        $candidates = [$feature, ...$this->stepsServing($cwd . '/' . $feature)];

        $spec = $recency->mostRecentSource($cwd . '/' . $this->config->specDir());
        $source = $recency->mostRecentSource(...$this->sourceDirectories($cwd));
        foreach ([$spec, $source] as $absolute) {
            if ($absolute !== null) {
                $candidates[] = ProjectPath::relativeOrNull($absolute) ?? $absolute;
            }
        }

        $files = [];
        foreach ($candidates as $rel) {
            if ($this->filesystem->exists($cwd . '/' . $rel)) {
                $files[$rel] = $this->filesystem->read($cwd . '/' . $rel);
            }
        }

        return $files;
    }

    /**
     * @return list<string>
     */
    private function sourceDirectories(string $cwd): array
    {
        return array_map(static fn(string $root): string => $cwd . '/' . $root, $this->config->sourceRoots());
    }

    /**
     * The steps files, project-relative, whose definitions serve a feature's
     * steps, whatever those files are called.
     *
     * @return list<string>
     */
    private function stepsServing(string $featureFile): array
    {
        if (!$this->filesystem->exists($featureFile)) {
            return [];
        }

        $texts = array_column(StepGenerator::parseSteps($this->filesystem->read($featureFile)), 'text');

        return array_map(
            static fn(string $file): string => ProjectPath::relative($file),
            (new StepVocabulary($this->filesystem))->filesServing($texts, ...(new StepsHome($this->config, $this->filesystem))->roots()),
        );
    }

    /**
     * Says why there will be no suggestion, as a receipt for a coding agent,
     * else in red.
     */
    private function refuse(Output $output, bool $forAgent, string $problem, ?string $remedy = null): int
    {
        if ($forAgent) {
            $json = json_encode(['v' => Schema::V, 'action' => 'next', 'error' => $problem] + ($remedy === null ? [] : ['remedy' => $remedy]), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
            $output->write($json . "\n", false, Output::OUTPUT_RAW);

            return 1;
        }

        $output->writeln('<fg=red>' . $problem . '</>');

        return 1;
    }

    /**
     * Loads the project's bootstrap. The file it could not find, or null.
     */
    private function loadBootstrap(): ?string
    {
        $bootstrap = $this->config->getBootstrap() ?? (file_exists('vendor/autoload.php') ? 'vendor/autoload.php' : null);
        if ($bootstrap === null) {
            return null;
        }
        if (!file_exists($bootstrap)) {
            return $bootstrap;
        }
        require_once $bootstrap;

        return null;
    }
}
