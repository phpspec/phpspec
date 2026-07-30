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
use PhpSpec\Ai\TreeScanner;
use PhpSpec\CodeGeneration\FeatureLayout;
use PhpSpec\Configuration;
use PhpSpec\Console\Command\Run\RecencyScanner;
use PhpSpec\Filesystem;
use PhpSpec\RealFilesystem;
use PhpSpec\StoryBDD\StepVocabulary;
use RuntimeException;
use Throwable;

/**
 * @internal
 * The one verb every AI command calls. One `chat()` resolves the command per its
 * manifest, resolves the current TDD step (the user's words first, the suite
 * state second), acts deterministically when the step fully determines the
 * artifact, and otherwise asks the model on the command's declared answer
 * channel, with a single corrective re-ask before failing cleanly. Every
 * exchange is captured to the debug recording. Tools only propose; the caller
 * confirms and applies through the Writer.
 */
final class Agent
{
    /**
     * The output-token ceiling when neither the user config nor the command
     * manifest sets one. Generous, because a reasoning model's thinking counts
     * against it: a tight cap comes back as an EMPTY response on both channels
     * (seen live with gemini-3.1-pro-preview at 8192).
     */
    private const DEFAULT_MAX_TOKENS = 16384;

    private readonly Filesystem $filesystem;

    private readonly ToolRegistry $registry;

    private readonly Recorder $recorder;

    private readonly PromptLibrary $prompts;

    private readonly FeatureLayout $layout;

    /** The conversation's standing project map, built once per session. */
    private ?string $projectMap = null;

    /**
     * @param Configuration $config the project configuration
     * @param Filesystem|null $filesystem filesystem abstraction for testability
     * @param ProviderInterface|null $provider injectable provider seam; built from the ai config when null
     * @param ToolRegistry|null $registry the shared tool definitions
     * @param Recorder|null $recorder captures every exchange
     * @param PromptLibrary|null $prompts loads the prompt files
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
    }

    /**
     * Runs one turn of the pipeline for a named command and an instruction:
     * the command's profile resolves through the prompt library (project
     * overrides first). A caller that already knows part of its world (a suite
     * it just ran, the recency it scanned) passes a seed grounding; the
     * manifest's remaining sections are filled in around it.
     */
    public function chat(string $command, string $instruction, ?Grounding $seed = null): Outcome
    {
        try {
            $profile = CommandProfile::compose($command, ...$this->prompts->stack('commands/' . $command));
        } catch (RuntimeException $e) {
            return new Outcome(null, [], $e->getMessage());
        }

        $grounding = $this->ground($profile, $instruction, $seed);
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

        return $this->ask($profile, $step, $grounding, $instruction, $aiConfig);
    }

    /**
     * Asks the model: composes the request from the prompt files, enforces the
     * declared answer channel (one corrective re-ask when a tool_call command
     * answers in prose), executes the tool calls into proposals, and captures
     * the exchange.
     *
     * @param array{provider: string, model?: string, api_key: string, maxTokens?: int, effort?: string}|null $aiConfig
     */
    private function ask(CommandProfile $profile, ?Step $step, Grounding $grounding, string $instruction, ?array $aiConfig): Outcome
    {
        $request = Request::compose($profile, $step, $grounding, $instruction, $this->prompts);

        try {
            $provider = $this->providerFor($aiConfig);
            $options = $this->options($profile, $aiConfig ?? []);
        } catch (RuntimeException|InvalidArgumentException $e) {
            $this->recorder->capture($profile->name, $instruction, $step, $request, $aiConfig ?? [], null);

            // A missing ai section stays an honest config error; any other
            // construction failure degrades like a failed call below.
            return $aiConfig === null
                ? new Outcome($step, [], $e->getMessage())
                : $this->failedAsk($step, $e->getMessage());
        }

        // The conversation: the caller's persistent transcript when one was
        // injected (a pair session), otherwise a fresh one for this exchange.
        // The system slot seats once per command; a swap re-orients it.
        $transcript = $this->transcript ?? new Transcript();
        $transcript->beginTurn();
        if (!$transcript->isOrientedFor($profile->name)) {
            $transcript->orient($profile->name, $this->orientation($request->system));
        }
        $this->situate($transcript, $step, $grounding);
        $transcript->say($request->context);
        $this->executor?->beginTurn();

        $rounds = [];
        $limit = $this->executor !== null ? ($profile->maxTurns ?? 50) : 1;
        $response = null;

        try {
            for ($turn = 0; $turn < $limit; $turn++) {
                $roundOptions = $options;
                if ($this->executor !== null) {
                    $roundOptions['tools'] = $this->executor->advertised();
                }

                $response = $provider->chat($transcript->messages(), $roundOptions);

                // The channel rail: structure only ever arrives as tool calls, so a
                // tool_call command answered in prose gets ONE corrective re-ask.
                // Providers that honour toolChoice (papi-core >= 0.13) make this a
                // rare fallback; older ones ignore the option and rely on it.
                if ($this->executor === null && $profile->answer === 'tool_call' && !$response->hasToolCalls()) {
                    $transcript->heard($response);
                    $transcript->say('Answer by calling exactly one of the declared tools; do not answer in prose.');
                    $response = $provider->chat($transcript->messages(), $roundOptions);
                }

                $transcript->heard($response);

                if ($this->executor === null || !$response->hasToolCalls()) {
                    break;
                }

                $results = [];
                foreach ($response->toolCalls as $toolCall) {
                    $result = $this->executor->execute($toolCall);
                    $results[$toolCall->id] = $result;
                    $transcript->observed($toolCall->id, $result);
                }
                $rounds[] = ['response' => $response, 'tool_results' => $results];

                foreach ($this->executor->observations() as $report) {
                    $transcript->say($report);
                }

                $handBack = $this->executor->turnComplete($response);
                if ($handBack !== null) {
                    $this->recorder->capture($profile->name, $instruction, $step, $request, $aiConfig ?? [], $response, [], $rounds);

                    return new Outcome($step, [], $handBack, $this->executor->lastSuggestion() ?? []);
                }
            }
        } catch (Throwable $e) {
            // A live provider failure (bad key, HTTP error, an unenforceable
            // toolChoice) becomes prose for the human, never a crash.
            $this->recorder->capture($profile->name, $instruction, $step, $request, $aiConfig ?? [], null, [], $rounds);

            return $this->failedAsk($step, $e->getMessage());
        }

        if ($this->executor !== null) {
            $ended = $response !== null && !$response->hasToolCalls();
            if ($ended) {
                $rounds[] = ['response' => $response];
            }
            $this->recorder->capture($profile->name, $instruction, $step, $request, $aiConfig ?? [], $response, [], $rounds);

            return new Outcome(
                $step,
                [],
                $ended ? trim($response->text) : 'Reached maximum tool turns. Please try a simpler request.',
                $this->executor->lastSuggestion() ?? [],
            );
        }

        if ($response === null) {
            // Unreachable in practice: the single propose-only round always ran
            // and its failures returned above; kept honest for the type.
            return $this->failedAsk($step, 'The provider returned no response.');
        }

        try {
            $proposals = $this->registry->fromCalls($response->toolCalls, $step);
        } catch (RuntimeException $e) {
            $this->recorder->capture($profile->name, $instruction, $step, $request, $aiConfig ?? [], $response);

            return new Outcome($step, [], $e->getMessage());
        }

        $data = $this->registry->reportFrom($response->toolCalls);
        $this->recorder->capture($profile->name, $instruction, $step, $request, $aiConfig ?? [], $response, $proposals);

        if ($profile->answer === 'tool_call' && $proposals === [] && $data === []) {
            // A write-feature ask that produced nothing usable still moves the
            // loop: the skeleton at the derived path stands in, and only there
            // (provider errors above stay errors).
            $fallback = $this->registry->featureFallback($step);
            if ($fallback !== null) {
                return new Outcome($step, [$fallback]);
            }

            $prose = trim($response->text);

            // Command-neutral: `next` has no instruction to rephrase, so the
            // fallback names the likely levers instead.
            return new Outcome($step, [], $prose !== '' ? $prose : 'The model returned no usable answer. Try again, or set ai.model to a stronger model.');
        }

        return new Outcome($step, $proposals, trim($response->text), $data);
    }

    /**
     * The system text for a transcript's orient slot: the composed prompt with
     * the project layout tokens resolved, plus, for a persistent conversation,
     * the standing project map (fresh state rides the per-turn situation).
     */
    private function orientation(string $system): string
    {
        if (str_contains($system, '%')) {
            $roots = $this->layout->roots($this->filesystem);
            $system = strtr($system, [
                '%spec_path%' => ltrim($this->config->getSpecPath(), './'),
                '%spec_suffix%' => $this->config->getSpecSuffix(),
                '%src_path%' => ltrim($this->config->getSrcPath(), './'),
                '%features_path%' => $roots['features'],
                '%steps_path%' => $roots['steps'],
            ]);
        }

        if ($this->transcript === null) {
            return $system;
        }

        $map = $this->projectMap();

        return $map === '' ? $system : $system . "\n\n" . $map;
    }

    /**
     * Grounds a conversational turn in the live suite state and the resolved
     * step, as the one fresh "[Current situation]" the window keeps.
     */
    private function situate(Transcript $transcript, ?Step $step, Grounding $grounding): void
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

    /**
     * The conversation's standing project map: the source, spec, and feature
     * trees plus the step titles the suite already owns, built once per
     * session for the orient slot.
     */
    private function projectMap(): string
    {
        if ($this->projectMap !== null) {
            return $this->projectMap;
        }

        $cwd = getcwd() ?: '.';
        $scanner = new TreeScanner($this->filesystem);
        $sections = [];

        $srcPath = ltrim($this->config->getSrcPath(), './');
        $srcTree = $scanner->scan($cwd . '/' . $srcPath, 3);
        if ($srcTree !== '') {
            $sections[] = "## Source files ($srcPath/)\n$srcTree";
        }

        $specPath = ltrim($this->config->getSpecPath(), './');
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

        $titles = $this->stepTitles($featuresDir);
        if ($titles !== '') {
            $sections[] = "## Existing step definitions\nThese steps are already defined, reuse them in new scenarios:\n$titles";
        }

        $this->projectMap = $sections === [] ? '' : "# Project file tree\n\n" . implode("\n\n", $sections);

        return $this->projectMap;
    }

    /**
     * The step titles the suite already owns, grouped by their file, from the
     * step vocabulary (titles are keyword-blind: each registers once).
     */
    private function stepTitles(string $featuresRoot): string
    {
        $byFile = [];
        foreach ((new StepVocabulary($this->filesystem))->definedTitles($featuresRoot) as $title => $file) {
            $byFile[basename($file)][] = $title;
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

    /**
     * The outcome of an ask the provider could not answer: for a write-feature
     * step the derived skeleton stands in so the loop still moves offline,
     * with the failure kept visible beside it; anything else is the error.
     */
    private function failedAsk(?Step $step, string $error): Outcome
    {
        $fallback = $this->registry->featureFallback($step);
        if ($fallback !== null) {
            return new Outcome($step, [$fallback], $error);
        }

        return new Outcome($step, [], $error);
    }

    /**
     * The provider to talk to: the injected seam, or one built from the ai
     * config; a missing config is reported like any other provider failure.
     *
     * @param array{provider: string, model?: string, api_key: string, maxTokens?: int, effort?: string}|null $aiConfig
     */
    private function providerFor(?array $aiConfig): ProviderInterface
    {
        if ($this->provider !== null) {
            return $this->provider;
        }

        if ($aiConfig === null) {
            throw new RuntimeException('AI configuration required. Add an "ai" section to your phpspec config.');
        }

        return ProviderFactory::create($aiConfig);
    }

    /**
     * The provider options for a command, plus the declared tools. Precedence
     * for model params: the user's phpspec config beats the shipped command
     * manifest, which beats the code default.
     *
     * @param array{provider?: string, model?: string, maxTokens?: int, effort?: string} $aiConfig
     * @return array<string, mixed>
     */
    private function options(CommandProfile $profile, array $aiConfig): array
    {
        $options = [
            'maxTokens' => $aiConfig['maxTokens'] ?? $profile->maxTokens ?? self::DEFAULT_MAX_TOKENS,
        ];

        // Reasoning effort is the user's call entirely; providers that cannot
        // map it yet simply ignore the option.
        if (isset($aiConfig['effort'])) {
            $options['effort'] = $aiConfig['effort'];
        }

        $model = $aiConfig['model'] ?? (isset($aiConfig['provider']) ? ProviderFactory::defaultModel($aiConfig['provider']) : null);
        if ($model !== null) {
            $options['model'] = $model;
        }

        if ($profile->temperature !== null) {
            $options['temperature'] = $profile->temperature;
        }

        $tools = $this->registry->definitions($profile);
        if ($tools !== []) {
            $options['tools'] = $tools;

            // Force the answer channel at the provider where supported
            // (papi-core >= 0.13); one declared tool is forced by name, several
            // leave the model the choice of tool but not of channel.
            if ($profile->answer === 'tool_call') {
                $options['toolChoice'] = count($profile->tools) === 1 ? ['name' => $profile->tools[0]] : 'required';
            }
        }

        return $options;
    }

    /**
     * Builds the grounding sections the command's manifest asks for, around
     * whatever the caller already seeded (a seeded section is never rebuilt).
     * The suite section has no builder here; it always comes from the seed.
     */
    private function ground(CommandProfile $profile, string $instruction, ?Grounding $seed): Grounding
    {
        $cwd = getcwd() ?: '.';
        $recentFeature = $seed?->recentFeature;
        $recentSource = $seed?->recentSource;
        $tree = $seed->tree ?? '';
        $namedFiles = $seed->namedFiles ?? [];

        if (in_array('recency', $profile->grounding, true) && $recentFeature === null && $recentSource === null) {
            $scanner = new RecencyScanner($this->filesystem);
            $recentFeature = ProjectPath::relativeOrNull($scanner->mostRecentFeature($cwd . '/' . trim($this->config->getFeaturesPath(), './')));
            $recentSource = ProjectPath::relativeOrNull($scanner->mostRecentSource($cwd . '/' . ltrim($this->config->getSrcPath(), './')));
        }

        if (in_array('tree', $profile->grounding, true) && $tree === '') {
            $scanner = new TreeScanner($this->filesystem);
            $sections = [];
            foreach ([ltrim($this->config->getSrcPath(), './'), ltrim($this->config->getSpecPath(), './')] as $dir) {
                // Deep enough to NAME files in a namespaced project (a one-level
                // scan of src/App/... shows nothing but "App/"), each tree
                // labelled with the directory it describes.
                $listing = $scanner->scan($cwd . '/' . $dir, 3);
                if ($listing !== '') {
                    $sections[] = "$dir/:\n" . $listing;
                }
            }
            $tree = implode("\n\n", $sections);
        }

        if (in_array('named_files', $profile->grounding, true) && $namedFiles === []) {
            $namedFiles = $this->namedFiles($instruction);
        }

        $polished = $seed->polished ?? [];
        if (in_array('journal', $profile->grounding, true) && $polished === []) {
            $polished = (new RefactorJournal($this->filesystem))->unchangedTargets($cwd . '/' . ltrim($this->config->getSrcPath(), './'));
        }

        return new Grounding($seed?->suite, $recentFeature, $recentSource, $tree, $namedFiles, $polished);
    }

    /**
     * The existing files the instruction names: any class-like token found by
     * basename under the configured spec and source trees, plus any explicit
     * path token that exists (a named .feature also brings its steps file
     * along), so the model edits what is really there even in a namespaced
     * project.
     *
     * @return array<string, string> relative path => contents
     */
    private function namedFiles(string $instruction): array
    {
        $cwd = getcwd() ?: '.';
        $specPath = ltrim($this->config->getSpecPath(), './');
        $srcPath = ltrim($this->config->getSrcPath(), './');
        $files = [];
        preg_match_all('/\b[A-Z][A-Za-z0-9]+\b/', $instruction, $matches);

        foreach (array_unique($matches[0]) as $class) {
            foreach ([[$srcPath, $class . '.php'], [$specPath, $class . $this->config->getSpecSuffix()]] as [$dir, $name]) {
                foreach ($this->findByName($cwd . '/' . $dir, $name) as $rel) {
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
                $steps = $this->layout->stepsPathFor($rel);
                if ($this->filesystem->exists($cwd . '/' . $steps)) {
                    $files[$steps] = $this->filesystem->read($cwd . '/' . $steps);
                }
            }
        }

        return $files;
    }

    /**
     * A bare class subject (no namespace, no path) resolved against the files
     * that actually exist: "TodoList" in a project holding
     * spec/App/TodoList.spec.php becomes "App\TodoList", so the derived path
     * updates the real spec instead of creating a flat sibling. An ambiguous or
     * unknown name is left as the user said it.
     */
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

        $resolved = $this->locateClass($step->subject);
        if ($resolved === null || $resolved === $step->subject) {
            return $step;
        }

        return new Step($step->phase, null, $resolved, $step->because . sprintf(', resolved to "%s" from the project tree', $resolved));
    }

    /**
     * The namespaced class path a bare name denotes, when exactly one existing
     * spec or source file matches it; null when none or several do.
     */
    private function locateClass(string $class): ?string
    {
        $cwd = getcwd() ?: '.';
        $specDir = ltrim($this->config->getSpecPath(), './');
        $srcDir = ltrim($this->config->getSrcPath(), './');
        $suffix = $this->config->getSpecSuffix();
        $prefix = trim($this->config->getPsr4Prefix(), '\\');

        $candidates = [];
        foreach ($this->findByName($cwd . '/' . $specDir, $class . $suffix) as $rel) {
            // Specs mirror the full namespace under the spec dir.
            $candidates[] = str_replace('/', '\\', substr($rel, 0, -strlen($suffix)));
        }
        foreach ($this->findByName($cwd . '/' . $srcDir, $class . '.php') as $rel) {
            // Source strips the PSR-4 prefix, so put it back for the class name.
            $bare = str_replace('/', '\\', substr($rel, 0, -strlen('.php')));
            $candidates[] = $prefix !== '' ? $prefix . '\\' . $bare : $bare;
        }

        $unique = array_values(array_unique($candidates));

        return count($unique) === 1 ? $unique[0] : null;
    }

    /**
     * Every file under a directory (to a sane depth) whose name is exactly the
     * given one, as paths relative to that directory.
     *
     * @return list<string>
     */
    private function findByName(string $root, string $fileName, int $depth = 0): array
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
                foreach ($this->findByName($full, $fileName, $depth + 1) as $childRel) {
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
