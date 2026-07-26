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
use PhpSpec\Ai\Message;
use PhpSpec\Ai\PromptLibrary;
use PhpSpec\Ai\ProviderFactory;
use PhpSpec\Ai\TreeScanner;
use PhpSpec\CodeGeneration\StepGenerator;
use PhpSpec\Configuration;
use PhpSpec\Console\Command\Run\RecencyScanner;
use PhpSpec\Filesystem;
use PhpSpec\RealFilesystem;
use RuntimeException;
use Throwable;

/**
 * @internal
 * The one verb every AI command calls. One `do()` grounds the command per its
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

    /**
     * @param Configuration $config the project configuration
     * @param Filesystem|null $filesystem filesystem abstraction for testability
     * @param ProviderInterface|null $provider injectable provider seam; built from the ai config when null
     * @param ToolRegistry|null $registry the shared tool definitions
     * @param Recorder|null $recorder captures every exchange
     * @param PromptLibrary|null $prompts loads the prompt files
     */
    public function __construct(
        private readonly Configuration $config,
        ?Filesystem $filesystem = null,
        private readonly ?ProviderInterface $provider = null,
        ?ToolRegistry $registry = null,
        ?Recorder $recorder = null,
        ?PromptLibrary $prompts = null,
    ) {
        $this->filesystem = $filesystem ?? new RealFilesystem();
        $this->registry = $registry ?? new ToolRegistry($config, $this->filesystem);
        $this->recorder = $recorder ?? new Recorder($this->filesystem);
        $this->prompts = $prompts ?? new PromptLibrary($this->filesystem);
    }

    /**
     * Runs one turn of the pipeline for a command and an instruction. A caller
     * that already knows part of its world (a suite it just ran, the recency it
     * scanned) passes a seed grounding; the manifest's remaining sections are
     * filled in around it.
     */
    public function do(CommandProfile $profile, string $instruction, ?Grounding $seed = null): Outcome
    {
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

            return new Outcome($step, [], $e->getMessage());
        }

        $messages = [Message::system($request->system), Message::user($request->context)];

        try {
            $response = $provider->chat($messages, $options);

            // The channel rail: structure only ever arrives as tool calls, so a
            // tool_call command answered in prose gets ONE corrective re-ask.
            // Providers that honour toolChoice (papi-core >= 0.13) make this a
            // rare fallback; older ones ignore the option and rely on it.
            if ($profile->answer === 'tool_call' && !$response->hasToolCalls()) {
                $messages[] = Message::assistant($response->text);
                $messages[] = Message::user('Answer by calling exactly one of the declared tools; do not answer in prose.');
                $response = $provider->chat($messages, $options);
            }
        } catch (Throwable $e) {
            // A live provider failure (bad key, HTTP error, an unenforceable
            // toolChoice) becomes prose for the human, never a crash.
            $this->recorder->capture($profile->name, $instruction, $step, $request, $aiConfig ?? [], null);

            return new Outcome($step, [], $e->getMessage());
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
            $prose = trim($response->text);

            // Command-neutral: `next` has no instruction to rephrase, so the
            // fallback names the likely levers instead.
            return new Outcome($step, [], $prose !== '' ? $prose : 'The model returned no usable answer. Try again, or set ai.model to a stronger model.');
        }

        return new Outcome($step, $proposals, trim($response->text), $data);
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

        return new Grounding($seed?->suite, $recentFeature, $recentSource, $tree, $namedFiles);
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
                $steps = StepGenerator::stepsPathFor($rel);
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
