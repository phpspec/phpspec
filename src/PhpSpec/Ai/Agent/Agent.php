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

namespace PhpSpec\Ai\Agent;

use InvalidArgumentException;
use PhpSpec\Ai\Contracts\ProviderInterface;
use PhpSpec\Ai\Contracts\ToolExecutor;
use PhpSpec\Ai\PromptLibrary;
use PhpSpec\Ai\ProviderFactory;
use PhpSpec\Ai\RefactorJournal;
use PhpSpec\Ai\Response;
use PhpSpec\Ai\TreeScanner;
use PhpSpec\CodeGeneration\FeatureLayout;
use PhpSpec\CodeGeneration\StepGenerator;
use PhpSpec\CodeGeneration\StepsHome;
use PhpSpec\Configuration;
use PhpSpec\Console\Command\Run\RecencyScanner;
use PhpSpec\Filesystem;
use PhpSpec\RealFilesystem;
use PhpSpec\StoryBDD\StepVocabulary;
use RuntimeException;
use Throwable;

/**
 * @internal
 * Resolves each AI command turn deterministically when possible, otherwise by asking the model.
 */
final class Agent
{
    /** Fallback output-token ceiling; a tight cap returns EMPTY from reasoning models (gemini-3.1-pro-preview at 8192). */

    private readonly Filesystem $filesystem;

    private readonly ToolRegistry $registry;

    private readonly Recorder $recorder;

    private readonly PromptLibrary $prompts;

    private readonly FeatureLayout $layout;

    private readonly StepsHome $steps;

    /** The conversation's standing project map, built once per session. */
    private ?string $projectMap = null;

    /** Whether this session's first turn already reset the session capture. */
    private bool $sessionCaptured = false;

    /**
     * @param Filesystem|null $filesystem filesystem abstraction for testability
     * @param ProviderInterface|null $provider injectable provider seam; built from the ai config when null
     * @param ToolRegistry|null $registry the shared tool definitions
     * @param Recorder|null $recorder captures every exchange
     * @param Transcript|null $transcript a persistent conversation; every chat() extends it instead of starting fresh
     * @param ToolExecutor|null $executor a live session's tool half; chat() loops and executes instead of proposing
     */
    public function __construct(
        private readonly Configuration $config,
        ?Filesystem $filesystem = null,
        private readonly ?ProviderInterface $provider = null,
        ?ToolRegistry $registry = null,
        ?Recorder $recorder = null,
        ?PromptLibrary $prompts = null,
        private readonly ?Transcript $transcript = null,
        private readonly ?ToolExecutor $executor = null,
    ) {
        $this->filesystem = $filesystem ?? new RealFilesystem();
        $this->prompts = $prompts ?? new PromptLibrary($this->filesystem);
        $this->registry = $registry ?? new ToolRegistry($config, $this->filesystem, $this->prompts);
        $this->recorder = $recorder ?? new Recorder($this->filesystem);
        $this->layout = new FeatureLayout();
        $this->steps = new StepsHome($config, $this->filesystem);
    }

    /**
     * Runs one turn of the pipeline for a named command and an instruction
     *
     * @param string $command the manifest name under Prompts/commands/ (e.g. "next", "generate"), project layer first
     * @param string $instruction what the human asked for, verbatim
     * @param Grounding|null $seed sections the caller already built; only the missing ones are gathered
     *
     * @return Outcome proposals to confirm and apply
     */
    public function chat(string $command, string $instruction, ?Grounding $seed = null): Outcome
    {
        try {
            $profile = CommandProfile::compose($command, ...$this->prompts->stack('commands/' . $command));
        } catch (RuntimeException $e) {
            return new Outcome(null, [], $e->getMessage());
        }

        $grounding = $this->groundingFor($profile, $instruction, $seed);
        $step = $this->refineSubject(Step::resolve($instruction, $grounding));
        $aiConfig = $this->config->getAiConfig();

        try {
            $proposals = $this->registry->deterministic($step, $grounding, $profile);
        } catch (RuntimeException $e) {
            $this->recorder->capture($profile->name, $instruction, $step, null, $aiConfig ?? [], null);

            return new Outcome($step, [], $e->getMessage());
        }

        if ($proposals !== null) {
            $this->recorder->capture($profile->name, $instruction, $step, null, $aiConfig ?? [], null, $proposals);

            return new Outcome($step, $proposals);
        }

        return $this->askTheModel($profile, $step, $grounding, $instruction, $aiConfig);
    }

    /**
     * @param array{provider: string, model?: string, maxTokens?: int, effort?: string, base_url?: string, api_key?: string}|null $aiConfig
     */
    private function askTheModel(CommandProfile $profile, ?Step $step, Grounding $grounding, string $instruction, ?array $aiConfig): Outcome
    {
        $request = Request::compose($profile, $step, $this->transcript !== null ? $grounding->withoutSuite() : $grounding, $instruction, $this->prompts);

        try {
            $provider = $this->providerFor($aiConfig);
            $options = $this->providerOptions($profile, $aiConfig ?? []);
        } catch (RuntimeException|InvalidArgumentException $e) {
            $this->recorder->capture($profile->name, $instruction, $step, $request, $aiConfig ?? [], null);

            return $aiConfig === null
                ? new Outcome($step, [], $e->getMessage())
                : $this->failureOutcome($step, $e->getMessage());
        }

        $transcript = $this->seatTranscript($profile, $request, $step, $grounding);

        return $this->executor === null
            ? $this->proposeInOneRound($provider, $options, $transcript, $profile, $step, $instruction, $request, $aiConfig)
            : $this->converseUntilHandBack($this->executor, $provider, $options, $transcript, $profile, $step, $instruction, $request, $aiConfig);
    }

    private function seatTranscript(CommandProfile $profile, Request $request, ?Step $step, Grounding $grounding): Transcript
    {
        $transcript = $this->transcript ?? new Transcript();
        $transcript->beginTurn();
        if (!$transcript->isOrientedFor($profile->name)) {
            $transcript->orient($profile->name, $this->orientation($request->system));
        }
        $this->situateTranscript($transcript, $step, $grounding);
        $transcript->say($request->context);

        return $transcript;
    }

    /**
     * @param array<string, mixed> $options
     * @param array{provider: string, model?: string, maxTokens?: int, effort?: string, base_url?: string, api_key?: string}|null $aiConfig
     */
    private function proposeInOneRound(ProviderInterface $provider, array $options, Transcript $transcript, CommandProfile $profile, ?Step $step, string $instruction, Request $request, ?array $aiConfig): Outcome
    {
        try {
            $response = $provider->chat($transcript->messages(), $options);

            // papi-core < 0.13 ignores toolChoice, so a prose answer gets one corrective re-ask.
            if ($profile->answer === 'tool_call' && !$response->hasToolCalls()) {
                $transcript->heard($response);
                $transcript->say('Answer by calling exactly one of the declared tools; do not answer in prose.');
                $response = $provider->chat($transcript->messages(), $options);
            }

            $transcript->heard($response);
        } catch (Throwable $e) {
            $this->captureTurn($profile, $instruction, $step, $request, $aiConfig, null, []);

            return $this->failureOutcome($step, $e->getMessage());
        }

        return $this->proposalsOutcome($response, $profile, $step, $instruction, $request, $aiConfig);
    }

    /**
     * @param array{provider: string, model?: string, maxTokens?: int, effort?: string, base_url?: string, api_key?: string}|null $aiConfig
     */
    private function proposalsOutcome(Response $response, CommandProfile $profile, ?Step $step, string $instruction, Request $request, ?array $aiConfig): Outcome
    {
        try {
            $proposals = $this->registry->fromCalls($response->toolCalls, $step);
        } catch (RuntimeException $e) {
            $this->recorder->capture($profile->name, $instruction, $step, $request, $aiConfig ?? [], $response);

            return new Outcome($step, [], $e->getMessage());
        }

        $data = $this->registry->reportFrom($response->toolCalls);
        $this->recorder->capture($profile->name, $instruction, $step, $request, $aiConfig ?? [], $response, $proposals);

        if ($profile->answer === 'tool_call' && $proposals === [] && $data === []) {
            $fallback = $this->registry->featureFallback($step);
            if ($fallback !== null) {
                return new Outcome($step, [$fallback]);
            }

            $prose = trim($response->text);

            return new Outcome($step, [], $prose !== '' ? $prose : 'The model returned no usable answer. Try again, or set ai.model to a stronger model.');
        }

        return new Outcome($step, $proposals, trim($response->text), $data);
    }

    /**
     * @param array<string, mixed> $options
     * @param array{provider: string, model?: string, maxTokens?: int, effort?: string, base_url?: string, api_key?: string}|null $aiConfig
     */
    private function converseUntilHandBack(ToolExecutor $executor, ProviderInterface $provider, array $options, Transcript $transcript, CommandProfile $profile, ?Step $step, string $instruction, Request $request, ?array $aiConfig): Outcome
    {
        $executor->beginTurn();

        $rounds = [];
        $limit = $profile->maxTurns ?? 50;
        $response = null;

        try {
            for ($turn = 0; $turn < $limit; $turn++) {
                $roundOptions = $options;
                $roundOptions['tools'] = $executor->advertised();

                $response = $provider->chat($transcript->messages(), $roundOptions);
                $transcript->heard($response);

                if (!$response->hasToolCalls()) {
                    $correction = $executor->correction($response);

                    if ($correction === null) {
                        break;
                    }

                    $rounds[] = ['response' => $response];
                    $transcript->say($correction);

                    continue;
                }

                $rounds[] = ['response' => $response, 'tool_results' => $this->executeToolCalls($executor, $response, $transcript)];

                foreach ($executor->observations() as $report) {
                    $transcript->say($report);
                }

                $handBack = $executor->turnComplete($response);
                if ($handBack !== null) {
                    $this->captureTurn($profile, $instruction, $step, $request, $aiConfig, $response, $rounds);

                    return new Outcome($step, [], $handBack, $executor->lastSuggestion() ?? []);
                }
            }
        } catch (Throwable $e) {
            $this->captureTurn($profile, $instruction, $step, $request, $aiConfig, null, $rounds);

            return $this->failureOutcome($step, $e->getMessage());
        }

        $ended = $response !== null && !$response->hasToolCalls();
        if ($ended) {
            $rounds[] = ['response' => $response];
        }
        $this->captureTurn($profile, $instruction, $step, $request, $aiConfig, $response, $rounds);

        return new Outcome(
            $step,
            [],
            $ended ? trim($response->text) : 'Reached maximum tool turns. Please try a simpler request.',
            $executor->lastSuggestion() ?? [],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function executeToolCalls(ToolExecutor $executor, Response $response, Transcript $transcript): array
    {
        $results = [];
        foreach ($response->toolCalls as $toolCall) {
            $result = $executor->execute($toolCall);
            $results[$toolCall->id] = $result;
            $transcript->observed($toolCall->id, $result);
        }

        return $results;
    }

    /**
     * @param array{provider: string, model?: string, maxTokens?: int, effort?: string, base_url?: string, api_key?: string}|null $aiConfig
     * @param list<array{response: Response, tool_results?: array<string, mixed>}> $rounds
     */
    private function captureTurn(CommandProfile $profile, string $instruction, ?Step $step, Request $request, ?array $aiConfig, ?Response $response, array $rounds): void
    {
        $this->recorder->capture($profile->name, $instruction, $step, $request, $aiConfig ?? [], $response, [], $rounds);

        if ($this->executor === null) {
            return;
        }

        $this->recorder->captureSession($profile->name, $instruction, $step, $aiConfig ?? [], $response, [], $rounds, !$this->sessionCaptured);
        $this->sessionCaptured = true;
    }

    private function orientation(string $system): string
    {
        if (str_contains($system, '%')) {
            $roots = $this->layout->roots($this->filesystem);
            $system = strtr($system, [
                '%spec_path%' => $this->config->specDir(),
                '%spec_suffix%' => $this->config->getSpecSuffix(),
                '%src_path%' => implode('/, ', $this->config->sourceRoots()),
                '%features_path%' => $roots['features'],
                '%steps_path%' => $this->steps->directory(),
            ]);
        }

        if ($this->transcript === null) {
            return $system;
        }

        $map = $this->projectMap();

        return $map === '' ? $system : $system . "\n\n" . $map;
    }

    private function situateTranscript(Transcript $transcript, ?Step $step, Grounding $grounding): void
    {
        if ($this->transcript === null || $grounding->suite === null) {
            return;
        }

        $report = SituationReport::fromSummary($grounding->suite)->render();
        if ($step !== null) {
            $report .= sprintf("\nCurrent step: %s (%s).", $step->phase->value, $step->because);
        }

        $transcript->situate($report);
    }

    private function projectMap(): string
    {
        if ($this->projectMap !== null) {
            return $this->projectMap;
        }

        $cwd = getcwd() ?: '.';
        $scanner = new TreeScanner($this->filesystem);
        $sections = [];

        foreach ($this->config->sourceRoots() as $srcPath) {
            $srcTree = $scanner->scan($cwd . '/' . $srcPath, 3);
            if ($srcTree !== '') {
                $sections[] = "## Source files ($srcPath/)\n$srcTree";
            }
        }

        $specPath = $this->config->specDir();
        $specTree = $scanner->scan($cwd . '/' . $specPath, 3);
        if ($specTree !== '') {
            $sections[] = "## Spec files ($specPath/)\n$specTree";
        }

        $featuresDir = $cwd . '/' . trim($this->config->getFeaturesPath(), './');
        if ($this->filesystem->exists($featuresDir) && $this->filesystem->isDir($featuresDir)) {
            $featTree = $scanner->scan($featuresDir, 3);
            if ($featTree !== '') {
                $sections[] = "## Feature files\n$featTree";
            }
        }

        $titles = $this->stepTitlesByFile();
        if ($titles !== '') {
            $sections[] = "## Existing step definitions\nThese steps are already defined, reuse them in new scenarios:\n$titles";
        }

        $this->projectMap = $sections === [] ? '' : "# Project file tree\n\n" . implode("\n\n", $sections);

        return $this->projectMap;
    }

    private function stepTitlesByFile(): string
    {
        $byFile = [];
        foreach ((new StepVocabulary($this->filesystem))->definedTitles(...$this->steps->roots()) as $title => $file) {
            $byFile[$this->steps->label(ProjectPath::relative($file))][] = $title;
        }

        $lines = [];
        foreach ($byFile as $file => $titles) {
            $lines[] = "# $file";
            foreach ($titles as $title) {
                $lines[] = "- $title";
            }
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    private function failureOutcome(?Step $step, string $error): Outcome
    {
        $fallback = $this->registry->featureFallback($step);
        if ($fallback !== null) {
            return new Outcome($step, [$fallback], $error, failure: $error);
        }

        return new Outcome($step, [], $error, failure: $error);
    }

    /**
     * @param array{provider: string, model?: string, maxTokens?: int, effort?: string, base_url?: string, api_key?: string}|null $aiConfig
     */
    private function providerFor(?array $aiConfig): ProviderInterface
    {
        if ($this->provider !== null) {
            return $this->provider;
        }

        if ($aiConfig === null) {
            throw new RuntimeException($this->config->aiConfigProblem() ?? 'AI configuration required. Add an "ai" section to your phpspec config.');
        }

        return ProviderFactory::create($aiConfig);
    }

    /**
     * @param array{provider?: string, model?: string, maxTokens?: int, effort?: string, base_url?: string, api_key?: string} $aiConfig
     * @return array<string, mixed>
     */
    private function providerOptions(CommandProfile $profile, array $aiConfig): array
    {
        $options = $profile->providerOptions($aiConfig);
        $tools = $this->registry->definitions($profile);
        if ($tools !== []) {
            $options['tools'] = $tools;

            // papi-core >= 0.13 enforces toolChoice at the provider; older versions ignore it.
            if ($profile->answer === 'tool_call') {
                $options['toolChoice'] = count($profile->tools) === 1 ? ['name' => $profile->tools[0]] : 'required';
            }
        }

        return $options;
    }

    private function groundingFor(CommandProfile $profile, string $instruction, ?Grounding $seed): Grounding
    {
        $cwd = getcwd() ?: '.';
        $recentFeature = $seed?->recentFeature;
        $recentSource = $seed?->recentSource;
        $tree = $seed->tree ?? '';
        $namedFiles = $seed->namedFiles ?? [];

        if (in_array('recency', $profile->grounding, true) && $recentFeature === null && $recentSource === null) {
            $scanner = new RecencyScanner($this->filesystem);
            $recentFeature = ProjectPath::relativeOrNull($scanner->mostRecentFeature($cwd . '/' . trim($this->config->getFeaturesPath(), './')));
            $recentSource = ProjectPath::relativeOrNull($scanner->mostRecentSource(...array_map(static fn(string $root): string => $cwd . '/' . $root, $this->config->sourceRoots())));
        }

        if (in_array('tree', $profile->grounding, true) && $tree === '') {
            $scanner = new TreeScanner($this->filesystem);
            $sections = [];
            foreach ([...$this->config->sourceRoots(), $this->config->specDir()] as $dir) {
                $listing = $scanner->scan($cwd . '/' . $dir, 3);
                if ($listing !== '') {
                    $sections[] = "$dir/:\n" . $listing;
                }
            }
            $tree = implode("\n\n", $sections);
        }

        if (in_array('named_files', $profile->grounding, true) && $namedFiles === []) {
            $namedFiles = $this->filesNamedIn($instruction);
        }

        $polished = $seed->polished ?? [];
        if (in_array('journal', $profile->grounding, true) && $polished === []) {
            $polished = (new RefactorJournal($this->filesystem))->unchangedTargets($cwd, $this->config->getSourceLayout());
        }

        return new Grounding($seed?->suite, $recentFeature, $recentSource, $tree, $namedFiles, $polished);
    }

    /**
     * @return array<string, string> relative path => contents
     */
    private function filesNamedIn(string $instruction): array
    {
        $cwd = getcwd() ?: '.';
        $specPath = $this->config->specDir();
        $files = [];
        preg_match_all('/\b[A-Z][A-Za-z0-9]+\b/', $instruction, $matches);

        foreach (array_unique($matches[0]) as $class) {
            $places = [...array_map(static fn(string $root): array => [$root, $class . '.php'], $this->config->sourceRoots()), [$specPath, $class . $this->config->getSpecSuffix()]];

            foreach ($places as [$dir, $name]) {
                foreach ($this->findFilesNamed($cwd . '/' . $dir, $name) as $rel) {
                    $files["$dir/$rel"] = $this->filesystem->read($cwd . '/' . $dir . '/' . $rel);
                }
            }
        }

        foreach (ProjectPath::tokensIn($instruction) as $named) {
            $rel = ProjectPath::normalize($named);
            if (!$this->filesystem->exists($cwd . '/' . $rel)) {
                continue;
            }

            $files[$rel] = $this->filesystem->read($cwd . '/' . $rel);

            if (str_ends_with($rel, '.feature')) {
                $files += $this->stepsServing($files[$rel]);
            }
        }

        return $files;
    }

    /**
     * The steps files whose definitions serve a feature's steps, whatever
     * those files are called.
     *
     * @return array<string, string> relative path => contents
     */
    private function stepsServing(string $feature): array
    {
        $texts = array_column(StepGenerator::parseSteps($feature), 'text');
        $files = [];
        foreach ((new StepVocabulary($this->filesystem))->filesServing($texts, ...$this->steps->roots()) as $file) {
            $files[ProjectPath::relative($file)] = $this->filesystem->read($file);
        }

        return $files;
    }

    private function refineSubject(?Step $step): ?Step
    {
        if ($step === null || $step->path !== null || $step->subject === null) {
            return $step;
        }

        if ($step->phase !== Phase::WriteSpec && $step->phase !== Phase::WriteCode) {
            return $step;
        }

        if (str_contains($step->subject, '\\') || str_contains($step->subject, '/')) {
            return $step;
        }

        $resolved = $this->uniqueClassFor($step->subject);
        if ($resolved !== null && $resolved !== $step->subject) {
            return new Step($step->phase, null, $resolved, $step->because . sprintf(', resolved to "%s" from the project tree', $resolved));
        }

        $namespace = $resolved === null ? $this->theOneNamespace() : null;
        if ($namespace === null) {
            return $step;
        }

        return new Step($step->phase, null, $namespace . '\\' . $step->subject, $step->because . sprintf(', placed under %s\\ as the project\'s namespace', $namespace));
    }

    /**
     * The namespace a new class named bare belongs under, as describe would
     * place it: the default namespace the config names, else the one the
     * project maps, or none when it maps several.
     */
    private function theOneNamespace(): ?string
    {
        $layout = $this->config->getSourceLayout();
        $mappings = $layout->mappings();

        return $layout->defaultNamespace() ?? (count($mappings) === 1 ? (string) array_key_first($mappings) : null);
    }

    private function uniqueClassFor(string $class): ?string
    {
        $cwd = getcwd() ?: '.';
        $specDir = $this->config->specDir();
        $suffix = $this->config->getSpecSuffix();
        $layout = $this->config->getSourceLayout();

        $candidates = [];
        foreach ($this->findFilesNamed($cwd . '/' . $specDir, $class . $suffix) as $rel) {
            $candidates[] = str_replace('/', '\\', substr($rel, 0, -strlen($suffix)));
        }
        foreach ($this->config->sourceRoots() as $srcDir) {
            foreach ($this->findFilesNamed($cwd . '/' . $srcDir, $class . '.php') as $rel) {
                $candidates[] = $layout->classAt(ltrim($srcDir . '/' . $rel, '/'));
            }
        }

        $unique = array_values(array_unique($candidates));

        return count($unique) === 1 ? $unique[0] : null;
    }

    /**
     * @return list<string>
     */
    private function findFilesNamed(string $root, string $fileName, int $depth = 0): array
    {
        if ($depth > 6 || !$this->filesystem->exists($root) || !$this->filesystem->isDir($root)) {
            return [];
        }

        $found = [];
        foreach ($this->filesystem->scandir($root) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $full = $root . '/' . $entry;
            if ($this->filesystem->isDir($full)) {
                foreach ($this->findFilesNamed($full, $fileName, $depth + 1) as $childRel) {
                    $found[] = $entry . '/' . $childRel;
                }

                continue;
            }

            if ($entry === $fileName) {
                $found[] = $entry;
            }
        }

        return $found;
    }
}
