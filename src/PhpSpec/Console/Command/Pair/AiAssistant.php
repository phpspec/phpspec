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

namespace PhpSpec\Console\Command\Pair;

use PhpSpec\Ai\Agent\Transcript;
use PhpSpec\Ai\Contracts\ProviderInterface;
use PhpSpec\Ai\PromptLibrary;
use PhpSpec\Ai\ProviderFactory;
use PhpSpec\Ai\TreeScanner;
use PhpSpec\Configuration;
use PhpSpec\Console\Command\Run\SuiteSummary;
use PhpSpec\Extensions\ExtensionLoader;
use PhpSpec\Filesystem;
use PhpSpec\RealFilesystem;
use Throwable;

/**
 * @internal
 * Handles natural-language input in pair mode via an LLM with tool use.
 *
 * Maintains full conversation history across calls so the LLM remembers
 * prior context (files generated, specs run, questions asked). The session's
 * tool half lives in the PairToolExecutor; this class runs the conversation.
 */
final class AiAssistant
{
    private bool $initialised = false;

    private Filesystem $filesystem;

    private const MAX_TURNS = 50;

    /**
     * The output-token ceiling per provider call when config does not set one.
     * Generous enough for a reasoning model to plan, write one artifact, and
     * report without truncation; `ai.max_tokens` in phpspec.yml can raise it.
     */
    private const DEFAULT_MAX_TOKENS = 16384;

    /** Resolves prompt files, project overrides first. */
    private readonly PromptLibrary $prompts;

    private readonly RoleState $roleState;

    /** The session's tool half: surfaces, live execution, and turn settling. */
    private readonly PairToolExecutor $executor;

    /** The session's conversation with the model, windowed across turns. */
    private readonly Transcript $transcript;

    /** Role-neutral guidance (DSL, tools, project conventions), cached once. */
    private string $baseGuidance = '';

    /** The scanned project context, cached once so /swap never re-scans. */
    private string $projectContext = '';

    /**
     * @param ProviderInterface $provider the AI provider for LLM interactions
     * @param Configuration $config the project configuration
     * @param PairOutput $output the pair-mode output helper
     * @param string|null $model the LLM model identifier, or null for the default
     * @param Filesystem|null $filesystem filesystem abstraction for testability
     * @param bool $interactive whether to prompt for user confirmation on write actions
     * @param ExtensionLoader|null $extensionLoader optional extension loader for custom tools
     * @param Chooser|null $chooser the interactive chooser; shared with the dispatcher
     *                              so "always" answers span the whole pair session
     * @param RoleState|null $roleState the current pairing role; shared with the
     *                                  dispatcher so a /swap is seen by both
     * @param SpecRunner|null $specRunner runs specs for run_specs and auto-verify;
     *                                    defaults to a fresh subprocess runner
     */
    public function __construct(
        private readonly ProviderInterface $provider,
        private readonly Configuration $config,
        private readonly PairOutput $output,
        private readonly ?string $model = null,
        ?Filesystem $filesystem = null,
        bool $interactive = true,
        ?ExtensionLoader $extensionLoader = null,
        ?Chooser $chooser = null,
        ?RoleState $roleState = null,
        ?SpecRunner $specRunner = null,
    ) {
        $this->filesystem = $filesystem ?? new RealFilesystem();
        $this->prompts = new PromptLibrary($this->filesystem);
        $this->roleState = $roleState ?? new RoleState();
        $this->executor = new PairToolExecutor(
            $config,
            $this->filesystem,
            $output,
            $chooser ?? new Chooser($output, $interactive),
            $this->roleState,
            $specRunner ?? new SubprocessRunner(),
            $this->prompts,
            $extensionLoader,
        );
        $this->transcript = new Transcript();
    }

    /**
     * Sends natural-language input to the LLM and displays the response.
     * Conversation history is preserved between calls.
     *
     * When a suite summary is supplied it is rendered as a fresh per-turn
     * grounding message ("[Current situation]") injected before the input, so
     * the model reacts to the same red/green reality the human sees. The message
     * is deliberately kept out of the cached system prompt.
     */
    public function handle(string $input, ?SuiteSummary $situation = null): void
    {
        $this->output->getOutput()->writeln('');
        $this->output->getOutput()->writeln('  <fg=gray>Thinking...</>');

        try {
            PairLogger::log('INPUT', $input);
            $this->ensureInitialised();
            $this->executor->beginTurn();
            $this->transcript->beginTurn();
            $this->injectSituation($situation);
            $this->transcript->say($input);

            $text = self::withoutMachineSuggestion($this->runLoop());

            if ($text !== '') {
                $this->output->getOutput()->writeln('');
                foreach (explode("\n", $text) as $line) {
                    $this->output->getOutput()->writeln("  $line");
                }
                $this->output->getOutput()->writeln('');
            }
        } catch (Throwable $e) {
            $this->output->error("AI error: {$e->getMessage()}");
        }
    }

    /**
     * The structured suggestion the model registered on its last turn (via the
     * suggest_next tool), or null when it registered none. The dispatcher turns
     * it into a ghost-prefilled /generate.
     *
     * @return array<string, string>|null
     */
    public function lastSuggestion(): ?array
    {
        return $this->executor->lastSuggestion();
    }

    /**
     * Strips a stray machine-readable {type,target,reason} suggestion block from
     * conversational output. That JSON is the standalone `next` command's contract
     * for a script to parse, never something the human should read in pair mode,
     * so an output rail removes it no matter why the model emitted it.
     */
    private static function withoutMachineSuggestion(string $text): string
    {
        $stripped = preg_replace('~\{(?=[^{}]*"type")(?=[^{}]*"target")(?=[^{}]*"reason")[^{}]*}~', '', $text);

        return trim((string) $stripped);
    }

    /**
     * Appends the per-turn grounding as a fresh user message, so the model sees
     * the suite's real state before it reads the input. It is a normal turn
     * message, never merged into the cached system prompt, so the built-once
     * prompt and /swap's reloadPrompt() stay untouched.
     */
    private function injectSituation(?SuiteSummary $situation): void
    {
        if ($situation === null) {
            return;
        }

        $report = SituationReport::fromSummary($situation, $this->roleState->current());
        $this->transcript->situate($report->render());
    }

    /**
     * Agentic loop: call provider, execute any tool calls through the session's
     * executor, repeat until the LLM returns a plain text response, the executor
     * settles the turn, or max turns is reached.
     */
    private function runLoop(): string
    {
        $options = [
            'model' => $this->model ?? ProviderFactory::defaultModel('google'),
            'maxTokens' => $this->maxTokens(),
            'temperature' => 0.3,
        ];

        // Reasoning effort is the user's call; providers that cannot map it yet
        // simply ignore the option.
        $effort = $this->config->getAiConfig()['effort'] ?? null;
        if ($effort !== null) {
            $options['effort'] = $effort;
        }

        for ($turn = 0; $turn < self::MAX_TURNS; $turn++) {
            $response = $this->provider->chat($this->transcript->messages(), ['tools' => $this->executor->advertised()] + $options);

            $this->transcript->heard($response);

            if (!$response->hasToolCalls()) {
                PairLogger::log('RESPONSE', trim($response->text));
                return trim($response->text);
            }

            foreach ($response->toolCalls as $toolCall) {
                $this->transcript->observed($toolCall->id, $this->executor->execute($toolCall));
            }

            foreach ($this->executor->observations() as $report) {
                $this->transcript->say($report);
            }

            $handBack = $this->executor->turnComplete($response);
            if ($handBack !== null) {
                return $handBack;
            }
        }

        return 'Reached maximum tool turns. Please try a simpler request.';
    }

    /**
     * The per-call output-token ceiling, taken from `ai.max_tokens` in config
     * when set, otherwise the generous default, so a slow reasoning model is
     * not cut off mid-answer, and a project can lift the cap further.
     */
    private function maxTokens(): int
    {
        $aiConfig = $this->config->getAiConfig();

        return $aiConfig['maxTokens'] ?? self::DEFAULT_MAX_TOKENS;
    }

    private function ensureInitialised(): void
    {
        if ($this->initialised) {
            return;
        }

        $this->initialised = true;

        $this->baseGuidance = $this->buildBaseGuidance();
        $this->projectContext = $this->buildProjectContext();

        $this->orientTranscript();
    }

    /**
     * Seats the system prompt for the current role: the role contract artifact
     * first, then the role-neutral guidance and the scanned project context.
     */
    private function orientTranscript(): void
    {
        $role = $this->roleState->current();

        $this->transcript->orient(
            $role->promptArtifact(),
            $this->loadRoleArtifact($role)
            . "\n\n" . $this->baseGuidance
            . "\n\n" . $this->projectContext,
        );
    }

    /**
     * Loads the role's prompt artifact (navigator.txt / driver.txt) through the
     * prompt library on the real filesystem (prompts are shipped package code),
     * falling back to a short inline contract when it cannot be read, so odd
     * packaging never yields a role-less prompt.
     */
    private function loadRoleArtifact(PairRole $role): string
    {
        $text = $this->prompts->read($role->promptArtifact());

        if (trim($text) === '') {
            return $role->aiIsNavigator()
                ? 'You are the NAVIGATOR. The human is driving. You never write files; you review and suggest, and the human triggers generation with the commands.'
                : 'You are the DRIVER. The human is navigating. Execute exactly what they direct, one artifact per turn, then show the diff, run the spec, and hand back.';
        }

        return $text;
    }

    /**
     * Re-orients the transcript for the current role after a /swap. The cached
     * base guidance and project context are reused, so swapping is cheap.
     */
    public function reloadPrompt(): void
    {
        if (!$this->initialised) {
            return;
        }

        $this->orientTranscript();
    }

    /**
     * Role-neutral guidance shared by both roles: the DSL, the tools, and the
     * project conventions. The role contract itself lives in the prompt artifacts.
     */
    private function buildBaseGuidance(): string
    {
        $featurePaths = $this->resolveFeaturePaths();

        // The role-neutral guidance is an editable prompt file (shipped package
        // code, so it loads from the real filesystem); only the project layout
        // is interpolated. Editing the pairing behaviour is a text edit.
        $guidance = $this->prompts->read('instructions/pair-guidance');
        if (trim($guidance) !== '') {
            return strtr($guidance, [
                '%spec_path%' => ltrim($this->config->getSpecPath(), './'),
                '%spec_suffix%' => $this->config->getSpecSuffix(),
                '%src_path%' => ltrim($this->config->getSrcPath(), './'),
                '%features_path%' => $featurePaths['features'],
                '%steps_path%' => $featurePaths['steps'],
            ]);
        }

        return 'You assist with phpspec 9 specs (describe/it/expect DSL), Gherkin features, and step definitions.';
    }

    /**
     * Scans project directories to build a file tree for the system prompt.
     */
    private function buildProjectContext(): string
    {
        $sections = [];
        $scanner = new TreeScanner($this->filesystem);

        $specPath = ltrim($this->config->getSpecPath(), './');
        $srcPath = ltrim($this->config->getSrcPath(), './');

        $srcTree = $scanner->scan(getcwd() . '/' . $srcPath, 3);
        if ($srcTree !== '') {
            $sections[] = "## Source files ($srcPath/)\n$srcTree";
        }

        $specTree = $scanner->scan(getcwd() . '/' . $specPath, 3);
        if ($specTree !== '') {
            $sections[] = "## Spec files ($specPath/)\n$specTree";
        }

        $featuresDir = getcwd() . '/' . trim($this->config->getFeaturesPath(), './');
        if ($this->filesystem->exists($featuresDir) && $this->filesystem->isDir($featuresDir)) {
            $featTree = $scanner->scan($featuresDir, 3);
            if ($featTree !== '') {
                $sections[] = "## Feature files\n$featTree";
            }
        }

        $stepsDir = $this->resolveFeaturePaths()['steps'];
        $stepSignatures = $this->scanStepSignatures(getcwd() . '/' . $stepsDir);
        if ($stepSignatures !== '') {
            $sections[] = "## Existing step definitions\nThese steps are already defined, reuse them in new scenarios:\n$stepSignatures";
        }

        if (empty($sections)) {
            return '';
        }

        return "# Project file tree\n\n" . implode("\n\n", $sections);
    }

    /**
     * Scans step definition files and extracts their signatures
     * (e.g. given('a spec file {string}:'), when('I run phpspec run'), etc.)
     * so the AI knows what steps are already available for reuse.
     */
    private function scanStepSignatures(string $stepsDir): string
    {
        if (!$this->filesystem->exists($stepsDir) || !$this->filesystem->isDir($stepsDir)) {
            return '';
        }

        $entries = $this->filesystem->scandir($stepsDir);
        $lines = [];

        foreach ($entries as $entry) {
            if (!str_ends_with($entry, '.steps.php')) {
                continue;
            }

            $content = $this->filesystem->read($stepsDir . '/' . $entry);
            if (preg_match_all('/\b(given|when|then)\s*\(\s*[\'"](.+?)[\'"]\s*,/i', $content, $matches)) {
                $lines[] = "# $entry";
                foreach ($matches[1] as $i => $keyword) {
                    $lines[] = "$keyword('{$matches[2][$i]}')";
                }
                $lines[] = '';
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Resolves the features and steps directories from the project layout.
     * Scans the features directory for a `steps/` subdirectory and a `scenarios/`
     * subdirectory (or uses `features/` directly for feature files).
     *
     * @return array{features: string, steps: string} relative paths
     */
    private function resolveFeaturePaths(): array
    {
        $base = getcwd() . '/features';

        $featuresPath = 'features/scenarios';
        $stepsPath = 'features/steps';

        if ($this->filesystem->exists($base) && $this->filesystem->isDir($base)) {
            $entries = $this->filesystem->scandir($base);

            if (!in_array('scenarios', $entries)) {
                $featuresPath = 'features';
            }

            if (!in_array('steps', $entries)) {
                // Look for a steps/ dir inside scenarios/
                $scenariosSteps = $base . '/scenarios/steps';
                if ($this->filesystem->exists($scenariosSteps) && $this->filesystem->isDir($scenariosSteps)) {
                    $stepsPath = 'features/scenarios/steps';
                }
            }
        }

        return ['features' => $featuresPath, 'steps' => $stepsPath];
    }
}
