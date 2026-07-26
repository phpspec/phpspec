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

use PhpSpec\Ai\AiTools;
use PhpSpec\Ai\Contracts\ProviderInterface;
use PhpSpec\Ai\Contracts\ToolInterface;
use PhpSpec\Ai\Message;
use PhpSpec\Ai\PromptLibrary;
use PhpSpec\Ai\ProviderFactory;
use PhpSpec\Ai\Response;
use PhpSpec\Ai\SymbolInspector;
use PhpSpec\Ai\Tool;
use PhpSpec\Ai\ToolCall;
use PhpSpec\CodeGeneration\LegacySpecDetector;
use PhpSpec\CodeGeneration\SpecGenerator;
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
 * prior context (files generated, specs run, questions asked).
 */
final class AiAssistant
{
    /** @var Message[] */
    private array $messages = [];

    /** @var array<string, ToolInterface> */
    private array $tools = [];

    private bool $initialised = false;

    private Filesystem $filesystem;

    private const MAX_TURNS = 50;

    /**
     * The output-token ceiling per provider call when config does not set one.
     * Generous enough for a reasoning model to plan, write one artifact, and
     * report without truncation; `ai.max_tokens` in phpspec.yml can raise it.
     */
    private const DEFAULT_MAX_TOKENS = 16384;

    /**
     * How many tool rounds a driving AI gets after its one artifact — enough to
     * run the spec and report — before the turn hands back to the human.
     */
    private const DRIVER_WRAPUP_ROUNDS = 4;

    private const WRITE_TOOLS = ['describe', 'add_example', 'generate_feature', 'generate_steps', 'write_file', 'update_file'];

    private readonly Chooser $chooser;

    private readonly RoleState $roleState;

    /** Runs specs and returns structured red/green, for run_specs and auto-verify. */
    private readonly SpecRunner $specRunner;

    /** Keeps the message history bounded, fresh, and focused across turns. */
    private readonly ConversationWindow $window;

    /** Role-neutral guidance (DSL, tools, project conventions), cached once. */
    private string $baseGuidance = '';

    /** The scanned project context, cached once so /swap never re-scans. */
    private string $projectContext = '';

    /** Index of the system message in the history, so /swap can rebuild only it. */
    private int $systemIndex = 0;

    /** Whether the AI has written its one artifact this turn (while driving). */
    private bool $artifactWrittenThisHandle = false;

    /** Tool rounds taken since the driving AI wrote its artifact this turn. */
    private int $postArtifactRounds = 0;

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
        private readonly bool $interactive = true,
        private readonly ?ExtensionLoader $extensionLoader = null,
        ?Chooser $chooser = null,
        ?RoleState $roleState = null,
        ?SpecRunner $specRunner = null,
    ) {
        $this->filesystem = $filesystem ?? new RealFilesystem();
        $this->chooser = $chooser ?? new Chooser($output, $interactive);
        $this->roleState = $roleState ?? new RoleState();
        $this->specRunner = $specRunner ?? new SubprocessRunner();
        $this->window = new ConversationWindow();
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
            $this->artifactWrittenThisHandle = false;
            $this->postArtifactRounds = 0;
            $this->messages = $this->window->apply($this->messages);
            $this->injectSituation($situation);
            $this->messages[] = Message::user($input);

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
     * Strips a stray machine-readable {type,target,reason} suggestion block from
     * conversational output. That JSON is the standalone `next` command's contract
     * for a script to parse — never something the human should read in pair mode —
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
        $this->messages[] = Message::user("[Current situation]\n" . $report->render());
    }

    /**
     * After the AI's one artifact lands this round, runs the suite and feeds the
     * fresh red/green back as a message, so the model learns the outcome of its
     * own change without having to choose to run. The run is a read — it never
     * consumes the one-artifact budget — and only fires the round the artifact
     * is written (not on later rounds of the same turn).
     */
    private function autoVerifyIfWritten(bool $hadArtifactBeforeThisRound): void
    {
        if ($hadArtifactBeforeThisRound || !$this->artifactWrittenThisHandle) {
            return;
        }

        $this->output->getOutput()->writeln('  <fg=gray>Verifying your change...</>');

        $outcome = $this->specRunner->run('', $this->output->getOutput());

        if ($outcome === null) {
            return;
        }

        $report = SituationReport::fromOutcome($outcome, $this->roleState->current());
        $this->messages[] = Message::user("[Auto-verify after your change]\n" . $report->render());
    }

    /**
     * Agentic loop: call provider, execute any tool calls, repeat until
     * the LLM returns a plain text response or max turns is reached.
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
            $response = $this->provider->chat($this->messages, ['tools' => $this->advertisedTools()] + $options);

            $this->messages[] = Message::assistant(
                $response->text,
                $response->toolCalls ?: null,
            );

            if (!$response->hasToolCalls()) {
                PairLogger::log('RESPONSE', trim($response->text));
                return trim($response->text);
            }

            $hadArtifact = $this->artifactWrittenThisHandle;

            foreach ($response->toolCalls as $toolCall) {
                $result = $this->executeTool($toolCall);
                $this->messages[] = Message::toolResult($toolCall->id, $result);
            }

            $this->autoVerifyIfWritten($hadArtifact);

            if ($this->driverTurnComplete($hadArtifact, $response)) {
                return $this->driverHandBack();
            }
        }

        return 'Reached maximum tool turns. Please try a simpler request.';
    }

    /**
     * The per-call output-token ceiling, taken from `ai.max_tokens` in config
     * when set, otherwise the generous default — so a slow reasoning model is
     * not cut off mid-answer, and a project can lift the cap further.
     */
    private function maxTokens(): int
    {
        $aiConfig = $this->config->getAiConfig();

        return $aiConfig['maxTokens'] ?? self::DEFAULT_MAX_TOKENS;
    }

    /**
     * Whether a driving AI's turn should hand back now. A driver takes one
     * artifact, then runs and reports — so once the artifact is written the turn
     * ends as soon as it reaches for another write (it is overreaching the goal),
     * or once it has used up its brief wrap-up window. Never applies while the AI
     * is navigating (the human's turn runs freely).
     */
    private function driverTurnComplete(bool $hadArtifactBeforeThisResponse, Response $response): bool
    {
        if (!$this->roleState->current()->aiIsDriver() || !$this->artifactWrittenThisHandle) {
            return false;
        }

        if ($hadArtifactBeforeThisResponse && $this->attemptsWrite($response)) {
            return true;
        }

        return ++$this->postArtifactRounds >= self::DRIVER_WRAPUP_ROUNDS;
    }

    /**
     * Whether any of a response's tool calls is a write.
     */
    private function attemptsWrite(Response $response): bool
    {
        foreach ($response->toolCalls as $toolCall) {
            if (in_array($toolCall->name, self::WRITE_TOOLS, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The line shown when a driving AI's turn ends after its one artifact.
     */
    private function driverHandBack(): string
    {
        return 'That\'s my one change for this turn. /run it, tell me the next step, or /swap to take the keyboard back.';
    }

    /**
     * The tools advertised to the model this turn, serialised for the provider.
     *
     * When the AI is navigating (the human drives) the write tools are withheld
     * entirely — the role is enforced by not offering them, not merely by asking
     * the model not to use them.
     *
     * @return list<array{name: string, description: string, input_schema: array<string, mixed>}>
     */
    private function advertisedTools(): array
    {
        $tools = array_values($this->tools);
        $role = $this->roleState->current();

        // Withhold the write tools when the AI is navigating (it never writes),
        // and — while it drives — once it has already written its one artifact
        // this turn, so it can only read and run for the rest of the turn.
        $withholdWrites = $role->aiIsNavigator()
            || ($role->aiIsDriver() && $this->artifactWrittenThisHandle);

        if ($withholdWrites) {
            $tools = array_values(array_filter(
                $tools,
                fn(ToolInterface $tool) => !in_array($tool->getName(), self::WRITE_TOOLS, true),
            ));
        }

        return array_map(
            fn(ToolInterface $tool) => [
                'name' => $tool->getName(),
                'description' => $tool->getDescription(),
                'input_schema' => $tool->getParameterSchema(),
            ],
            $tools,
        );
    }

    private function executeTool(ToolCall $toolCall): mixed
    {
        $tool = $this->tools[$toolCall->name] ?? null;

        if ($tool === null) {
            return ['error' => "Unknown tool: $toolCall->name"];
        }

        $isWrite = in_array($toolCall->name, self::WRITE_TOOLS, true);

        // Auto-refuse a write the role or one-artifact rule forbids — before it
        // is shown, so a refused write never appears on screen as if it ran.
        if ($isWrite) {
            $refusal = $this->autoRefuseWrite();

            if ($refusal !== null) {
                return $refusal;
            }
        }

        $this->output->getOutput()->writeln($this->formatToolDisplay($toolCall));
        PairLogger::log('TOOL', "$toolCall->name(" . json_encode($toolCall->arguments) . ')');

        // Writes the role allows still need the user's go-ahead, shown against
        // the driver's stated plan so the navigator confirms a real decision.
        if ($isWrite && !$this->chooser->choose($this->confirmQuestion($toolCall), 'write-files', 'apply file changes')) {
            PairLogger::log('RESULT', 'User declined');

            return self::declineSteer();
        }

        try {
            $result = $tool->execute($toolCall->arguments);

            // A write that came back with an error (e.g. a rejected legacy-style
            // spec) did not land, so it does not count as the turn's artifact —
            // the model may retry it in the correct form this same turn.
            if ($isWrite && !(is_array($result) && isset($result['error']))) {
                $this->artifactWrittenThisHandle = true;
            }

            PairLogger::log('RESULT', is_string($result) ? $result : (json_encode($result) ?: ''));

            return $result;
        } catch (Throwable $e) {
            PairLogger::log('RESULT', "ERROR: {$e->getMessage()}");

            return ['error' => $e->getMessage()];
        }
    }

    /**
     * The confirm-step prompt: the driver's stated plan (the write tool's
     * `intent`) so the navigator sees what they are agreeing to, falling back to
     * a bare go-ahead when no plan was given.
     */
    private function confirmQuestion(ToolCall $toolCall): string
    {
        $intent = $toolCall->arguments['intent'] ?? null;

        if (is_string($intent) && trim($intent) !== '') {
            return 'Plan: ' . trim($intent) . "\n  Apply this change?";
        }

        return 'Allow this action?';
    }

    /**
     * The reply handed back to the model when the navigator declines a write at
     * the confirm step: it steers back into the cycle — re-clarify, don't retry.
     *
     * @return array{error: string}
     */
    private static function declineSteer(): array
    {
        return ['error' => 'You declined this step. Don\'t repeat the same write — ask what I should '
            . 'change instead, then re-plan. Or /swap to take the keyboard back.'];
    }

    /**
     * Refuses a write that the role or the one-artifact rule forbids, returning
     * the error payload to send back to the model — the AI never writes while
     * navigating, and only one artifact per turn while driving. Returns null when
     * the write is allowed (subject to the user's go-ahead).
     *
     * @return array{error: string}|null
     */
    private function autoRefuseWrite(): ?array
    {
        $role = $this->roleState->current();

        if ($role->aiIsNavigator()) {
            PairLogger::log('RESULT', 'Navigator refusal');

            return ['error' => 'I\'m navigating — you\'re driving, so I won\'t write files. Use the commands (/describe, /exemplify, /run), or /swap to hand me the keyboard.'];
        }

        if ($role->aiIsDriver() && $this->artifactWrittenThisHandle) {
            PairLogger::log('RESULT', 'One artifact per turn');

            return ['error' => 'One artifact per turn while I drive. Let\'s run or inspect what we have, then continue on the next turn.'];
        }

        return null;
    }

    private function ensureInitialised(): void
    {
        if ($this->initialised) {
            return;
        }

        $this->initialised = true;

        foreach ($this->buildTools() as $tool) {
            $this->tools[$tool->getName()] = $tool;
        }

        $this->baseGuidance = $this->buildBaseGuidance();
        $this->projectContext = $this->buildProjectContext();

        $this->messages[] = $this->buildSystemMessage();
        $this->systemIndex = array_key_last($this->messages);
    }

    /**
     * Builds the system message for the current role: the role contract artifact
     * first, then the role-neutral guidance and the scanned project context.
     */
    private function buildSystemMessage(): Message
    {
        return Message::system(
            $this->loadRoleArtifact($this->roleState->current())
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
        $text = (new PromptLibrary())->read($role->promptArtifact());

        if (trim($text) === '') {
            return $role->aiIsNavigator()
                ? 'You are the NAVIGATOR. The human is driving. You never write files; you review and suggest, and the human triggers generation with the commands.'
                : 'You are the DRIVER. The human is navigating. Execute exactly what they direct, one artifact per turn, then show the diff, run the spec, and hand back.';
        }

        return $text;
    }

    /**
     * Rebuilds only the system message for the current role after a /swap. The
     * cached base guidance and project context are reused, so swapping is cheap.
     */
    public function reloadPrompt(): void
    {
        if (!$this->initialised) {
            return;
        }

        $this->messages[$this->systemIndex] = $this->buildSystemMessage();
    }

    /**
     * @return ToolInterface[]
     */
    private function buildTools(): array
    {
        $tools = [
            $this->describeTool(),
            $this->addExampleTool(),
            $this->generateFeatureTool(),
            $this->generateStepsTool(),
            $this->writeFileTool(),
            $this->updateFileTool(),
            $this->runSpecsTool(),
            $this->inspectSymbolTool(),
            $this->askUserTool(),
            AiTools::readFile($this->filesystem),
            AiTools::listFiles($this->filesystem),
        ];

        if ($this->extensionLoader !== null) {
            foreach ($this->extensionLoader->getToolProviders() as $provider) {
                foreach ($provider->getTools() as $tool) {
                    $tools[] = $tool;
                }
            }
        }

        return $tools;
    }

    private function askUserTool(): Tool
    {
        $chooser = $this->chooser;

        return Tool::make(
            name: 'ask_user',
            description: 'Ask the user a strictly YES/NO question before proceeding — it shows a '
                . 'Yes / always / No chooser. Use it ONLY for a binary confirmation (e.g. "shall I '
                . 'generate fooBar()?"). NEVER use it for an open-ended question such as "what should '
                . 'the arguments be?" — ask those in plain text and end your turn so the user can type '
                . 'a full answer at the prompt. Returns "yes", "always" (yes now and for all similar '
                . 'future questions), or "no".',
            parameters: [
                'question' => [
                    'type' => 'string',
                    'description' => 'The question to show the user, e.g. "Do you want me to generate the method fooBar()?"',
                ],
                'action' => [
                    'type' => 'string',
                    'description' => 'Short verb phrase naming the action, completing the sentence "always ..." (e.g. "generate methods")',
                ],
            ],
            handler: function (array $args) use ($chooser) {
                $kind = 'ai-' . preg_replace('/[^a-z0-9]+/', '-', strtolower($args['action']));

                if (!$chooser->choose($args['question'], $kind, $args['action'])) {
                    return 'no';
                }

                return $chooser->hasAlways($kind) ? 'always' : 'yes';
            },
        );
    }

    /**
     * The shared `intent` parameter for every write tool: the driver's one-line
     * plan, surfaced to the navigator at the confirm prompt so the go-ahead is a
     * real decision, not a blind "allow this action?".
     *
     * @return array{type: string, description: string}
     */
    private static function intentParameter(): array
    {
        return [
            'type' => 'string',
            'description' => 'One line stating what this change does and why, shown to the '
                . 'navigator at the confirm prompt (e.g. "add an example that total() sums the line items").',
        ];
    }

    /**
     * Whether spec content is written in the legacy phpspec 8 ObjectBehavior
     * style rather than the phpspec 9 describe()/it()/expect() DSL, via the
     * shared detector so this guard and the agent pipeline's never drift apart.
     */
    private static function isLegacySpec(string $content): bool
    {
        return LegacySpecDetector::looksLegacy($content);
    }

    /**
     * The error handed back to the model when it writes a legacy-style spec, so
     * it regenerates in the phpspec 9 DSL rather than the write landing.
     *
     * @return array{error: string}
     */
    private static function legacySpecRejection(): array
    {
        return ['error' => 'That is phpspec 8 ObjectBehavior syntax, which this project does not use. '
            . 'Write the spec in the phpspec 9 DSL instead: '
            . 'describe(Calculator::class, function () { it(\'adds numbers\', function () { expect((new Calculator())->add(2, 3))->toBe(5); }); }); '
            . '— no spec class, no "extends ObjectBehavior", no shouldHaveType/shouldReturn. '
            . 'Preserve the existing examples; do not replace them with a single initializable check.'];
    }

    /**
     * Guards a raw write (write_file/update_file) whose target resolves under the
     * spec directory — the path arbitrary content can otherwise take around the
     * spec tools. Rejects phpspec 8 syntax and any rewrite that would drop
     * existing examples, so both bypasses of the describe/add_example surface are
     * closed. Returns the rejection payload, or null when the write may proceed.
     *
     * @return array{error: string}|null
     */
    private static function specWriteRejection(string $absPath, string $specDir, string $newContent, string $oldContent): ?array
    {
        // The write path is built with "/" while the spec dir uses the platform
        // separator, so normalise both before comparing — otherwise the guard
        // silently misses on Windows (mixed "\" and "/") and the write leaks through.
        if (!str_starts_with(self::normalisePath($absPath), self::normalisePath($specDir))) {
            return null;
        }

        if (self::isLegacySpec($newContent)) {
            return self::legacySpecRejection();
        }

        if ($oldContent !== '' && self::countExamples($newContent) < self::countExamples($oldContent)) {
            return self::destructiveSpecRejection();
        }

        return null;
    }

    /**
     * The error handed back when a spec write would delete existing examples, so
     * the model grows the spec with add_example instead of overwriting it.
     *
     * @return array{error: string}
     */
    private static function destructiveSpecRejection(): array
    {
        return ['error' => 'That rewrite would drop existing examples from the spec. Specs grow, they '
            . 'are not overwritten — add behaviour with add_example, one example at a time. If you truly '
            . 'mean to remove an example, say why first and get the navigator\'s yes.'];
    }

    /**
     * Counts the it()/xit()/fit() examples in spec content, so a rewrite that
     * leaves fewer than it started with can be recognised as destructive.
     */
    private static function countExamples(string $content): int
    {
        return (int) preg_match_all('/\b[xf]?it\s*\(/', $content);
    }

    /**
     * The spec directory as an absolute path prefix, for testing whether a write
     * target lands under it.
     */
    private function specDir(): string
    {
        return getcwd() . DIRECTORY_SEPARATOR . ltrim($this->config->getSpecPath(), './') . DIRECTORY_SEPARATOR;
    }

    /**
     * Normalises a path to forward slashes so "under the spec dir" comparisons
     * hold regardless of the platform separator (and the mixed "\"/"/" a write
     * path can carry on Windows).
     */
    private static function normalisePath(string $path): string
    {
        return str_replace('\\', '/', $path);
    }

    /**
     * Writes a generated file, creating parent directories as needed, then
     * shows a diff when the file already existed or a full listing when it is
     * new. This keeps an overwrite of an existing spec/feature from being
     * rendered as an all-new file with every line marked added.
     *
     * @param Filesystem $filesystem the filesystem abstraction
     * @param PairOutput $output the pair-mode output helper
     * @param string $filePath the absolute path to write
     * @param string $content the file content
     */
    private static function writeGenerated(Filesystem $filesystem, PairOutput $output, string $filePath, string $content): void
    {
        $existed = $filesystem->exists($filePath);
        $oldContent = $existed ? $filesystem->read($filePath) : '';

        $dir = dirname($filePath);
        if (!$filesystem->exists($dir)) {
            $filesystem->mkdir($dir);
        }

        $filesystem->write($filePath, $content);

        if ($existed) {
            $output->fileDiff($filePath, $oldContent, $content);
        } else {
            $output->fileDisplay($filePath, $content, true);
        }
    }

    private function describeTool(): Tool
    {
        $specPath = ltrim($this->config->getSpecPath(), './');
        $specSuffix = $this->config->getSpecSuffix();
        $filesystem = $this->filesystem;
        $output = $this->output;

        return Tool::make(
            name: 'describe',
            description: 'Start a spec for a PHP class: writes an empty describe() skeleton in the phpspec 9 DSL, with no examples. Idempotent — does nothing if the spec already exists. This is how a new spec begins; then add behaviour one example at a time with add_example. There is no whole-file spec write and no way to overwrite a spec, so existing examples are never lost.',
            parameters: [
                'class_name' => [
                    'type' => 'string',
                    'description' => 'Class path using forward slashes (e.g. "App/Calculator")',
                ],
                'intent' => self::intentParameter(),
            ],
            handler: function (array $args) use ($specPath, $specSuffix, $filesystem, $output) {
                $classPath = $args['class_name'];

                $filePath = getcwd() . DIRECTORY_SEPARATOR
                    . $specPath . DIRECTORY_SEPARATOR
                    . str_replace('/', DIRECTORY_SEPARATOR, $classPath)
                    . $specSuffix;

                if ($filesystem->exists($filePath)) {
                    $output->getOutput()->writeln(sprintf(
                        '  <fg=gray>Spec already exists: %s</>',
                        $filePath,
                    ));

                    return "Spec for $classPath already exists at $filePath; no change made.";
                }

                $generator = new SpecGenerator($specPath, $filesystem, $specSuffix);
                $generator->generate($classPath);

                $output->fileDisplay($filePath, $filesystem->read($filePath), true);

                return "Spec skeleton for $classPath created at $filePath. Add behaviour with add_example.";
            },
        );
    }

    private function addExampleTool(): Tool
    {
        $specPath = ltrim($this->config->getSpecPath(), './');
        $specSuffix = $this->config->getSpecSuffix();
        $filesystem = $this->filesystem;
        $output = $this->output;

        return Tool::make(
            name: 'add_example',
            description: 'Add ONE it() example for a method to a spec, appending it without rewriting the rest of the file (creating the spec first if it does not exist). Idempotent: does nothing if that method is already exemplified. This is the only way to add behaviour to a spec — specs are never overwritten.',
            parameters: [
                'class_name' => [
                    'type' => 'string',
                    'description' => 'Class path using forward slashes (e.g. "App/Calculator")',
                ],
                'method' => [
                    'type' => 'string',
                    'description' => 'The method name to add an example for (e.g. "add")',
                ],
                'intent' => self::intentParameter(),
            ],
            handler: function (array $args) use ($specPath, $specSuffix, $filesystem, $output) {
                $classPath = $args['class_name'];
                $method = $args['method'];

                $filePath = getcwd() . DIRECTORY_SEPARATOR
                    . $specPath . DIRECTORY_SEPARATOR
                    . str_replace('/', DIRECTORY_SEPARATOR, $classPath)
                    . $specSuffix;

                $generator = new SpecGenerator($specPath, $filesystem, $specSuffix);

                $existed = $filesystem->exists($filePath);
                if (!$existed) {
                    $generator->generate($classPath);
                }

                $before = $existed ? $filesystem->read($filePath) : '';
                $added = $generator->addExample($classPath, $method);

                if (!$added && $existed) {
                    $output->getOutput()->writeln(sprintf(
                        '  <fg=gray>An example for %s::%s already exists.</>',
                        str_replace('/', '\\', $classPath),
                        $method,
                    ));

                    return "Example for $classPath::$method already exists; no change made.";
                }

                $after = $filesystem->read($filePath);
                if ($existed) {
                    $output->fileDiff($filePath, $before, $after);
                } else {
                    $output->fileDisplay($filePath, $after, true);
                }

                return "Example for $classPath::$method added to $filePath.";
            },
        );
    }

    private function generateFeatureTool(): Tool
    {
        $filesystem = $this->filesystem;
        $output = $this->output;
        $featuresPath = $this->resolveFeaturePaths()['features'];

        return Tool::make(
            name: 'generate_feature',
            description: 'Write a Gherkin .feature file. Provide complete feature content with Feature:, Scenario:, Given/When/Then steps.',
            parameters: [
                'feature_name' => [
                    'type' => 'string',
                    'description' => 'Feature file name without extension (e.g. "user-registration")',
                ],
                'content' => [
                    'type' => 'string',
                    'description' => 'The complete Gherkin feature file content',
                ],
                'intent' => self::intentParameter(),
            ],
            handler: function (array $args) use ($filesystem, $output, $featuresPath) {
                $name = $args['feature_name'];
                $content = $args['content'];

                $filePath = getcwd() . '/' . $featuresPath . '/' . $name . '.feature';

                self::writeGenerated($filesystem, $output, $filePath, $content);

                return "Feature file written to $filePath";
            },
        );
    }

    private function generateStepsTool(): Tool
    {
        $filesystem = $this->filesystem;
        $output = $this->output;
        $stepsPath = $this->resolveFeaturePaths()['steps'];

        return Tool::make(
            name: 'generate_steps',
            description: 'Write a .steps.php file with step definitions for a feature. Uses bare given(), when(), then() global functions (NO use/import statements for them). Placeholders: {string}, {int}.',
            parameters: [
                'feature_name' => [
                    'type' => 'string',
                    'description' => 'Step file name without extension (e.g. "user-registration")',
                ],
                'content' => [
                    'type' => 'string',
                    'description' => 'The complete PHP step definitions file content',
                ],
                'intent' => self::intentParameter(),
            ],
            handler: function (array $args) use ($filesystem, $output, $stepsPath) {
                $name = $args['feature_name'];
                $content = $args['content'];

                $filePath = getcwd() . '/' . $stepsPath . '/' . $name . '.steps.php';

                self::writeGenerated($filesystem, $output, $filePath, $content);

                return "Steps file written to $filePath";
            },
        );
    }

    private function writeFileTool(): Tool
    {
        $filesystem = $this->filesystem;
        $output = $this->output;
        $specDir = $this->specDir();

        return Tool::make(
            name: 'write_file',
            description: 'Create a new file with the given content. Use this for source and other files not covered by describe/generate_feature/generate_steps. Specs are written with describe/add_example, not here.',
            parameters: [
                'path' => [
                    'type' => 'string',
                    'description' => 'Relative path from project root (e.g. "src/App/Service.php")',
                ],
                'content' => [
                    'type' => 'string',
                    'description' => 'The complete file content',
                ],
                'intent' => self::intentParameter(),
            ],
            handler: function (array $args) use ($filesystem, $output, $specDir) {
                $path = $args['path'];
                $content = $args['content'];
                $absPath = getcwd() . '/' . ltrim($path, '/');

                if ($filesystem->exists($absPath)) {
                    return "File already exists: $path. Use update_file to modify it.";
                }

                $rejection = self::specWriteRejection($absPath, $specDir, $content, '');
                if ($rejection !== null) {
                    return $rejection;
                }

                $dir = dirname($absPath);
                if (!$filesystem->exists($dir)) {
                    $filesystem->mkdir($dir);
                }

                $filesystem->write($absPath, $content);
                $output->fileDisplay($absPath, $content, true);

                return "File written to $absPath";
            },
        );
    }

    private function updateFileTool(): Tool
    {
        $filesystem = $this->filesystem;
        $output = $this->output;
        $specDir = $this->specDir();

        return Tool::make(
            name: 'update_file',
            description: 'Update an existing file with new content. Use this to modify source files, config files, or any existing file. To change a spec, add behaviour with add_example — a spec rewrite that drops existing examples is rejected.',
            parameters: [
                'path' => [
                    'type' => 'string',
                    'description' => 'Relative path from project root (e.g. "src/App/Service.php")',
                ],
                'content' => [
                    'type' => 'string',
                    'description' => 'The complete new file content',
                ],
                'intent' => self::intentParameter(),
            ],
            handler: function (array $args) use ($filesystem, $output, $specDir) {
                $path = $args['path'];
                $content = $args['content'];
                $absPath = getcwd() . '/' . ltrim($path, '/');

                if (!$filesystem->exists($absPath)) {
                    return "File not found: $path. Use write_file to create it.";
                }

                $oldContent = $filesystem->read($absPath);

                $rejection = self::specWriteRejection($absPath, $specDir, $content, $oldContent);
                if ($rejection !== null) {
                    return $rejection;
                }

                $filesystem->write($absPath, $content);
                $output->fileDiff($absPath, $oldContent, $content);

                return "File updated: $absPath";
            },
        );
    }

    private function runSpecsTool(): Tool
    {
        $output = $this->output;
        $specRunner = $this->specRunner;
        $roleState = $this->roleState;

        return Tool::make(
            name: 'run_specs',
            description: 'Run phpspec specs via subprocess. Returns the suite\'s red/green state with the failing and pending examples and their errors, so you can report results and decide the next step.',
            parameters: [
                'path' => [
                    'type' => 'string',
                    'description' => 'Optional path to run (e.g. "spec/App/Calculator.spec.php"). Leave empty to run all.',
                    'default' => '',
                ],
            ],
            handler: function (array $args) use ($output, $specRunner, $roleState) {
                $path = $args['path'] ?? '';

                $output->getOutput()->writeln('  <fg=gray>Running specs...</>');

                $outcome = $specRunner->run($path, $output->getOutput());

                return SituationReport::fromOutcome($outcome, $roleState->current())->render();
            },
        );
    }

    private function inspectSymbolTool(): Tool
    {
        $inspector = new SymbolInspector(
            ltrim($this->config->getSrcPath(), './'),
            $this->config->getPsr4Prefix(),
            $this->filesystem,
        );

        return Tool::make(
            name: 'inspect_symbol',
            description: 'Inspect a PHP class, interface or trait by its fully-qualified name: whether it exists (is autoloadable), where its file is, and its real public method signatures from Reflection. Use this to learn what a type actually offers BEFORE writing a spec or a call against it. A symbol that does not exist yet is reported cleanly as such — it will not mislead you into thinking a file is merely missing.',
            parameters: [
                'fqcn' => [
                    'type' => 'string',
                    'description' => 'Fully-qualified class name, e.g. "App\\Calculator"',
                ],
            ],
            handler: fn(array $args) => $inspector->describe($args['fqcn']),
        );
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
        $guidance = (new PromptLibrary())->read('instructions/pair-guidance');
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

        $specPath = ltrim($this->config->getSpecPath(), './');
        $srcPath = ltrim($this->config->getSrcPath(), './');

        $srcTree = $this->scanTree(getcwd() . '/' . $srcPath, 3);
        if ($srcTree !== '') {
            $sections[] = "## Source files ($srcPath/)\n$srcTree";
        }

        $specTree = $this->scanTree(getcwd() . '/' . $specPath, 3);
        if ($specTree !== '') {
            $sections[] = "## Spec files ($specPath/)\n$specTree";
        }

        $featuresDir = getcwd() . '/features';
        if ($this->filesystem->exists($featuresDir) && $this->filesystem->isDir($featuresDir)) {
            $featTree = $this->scanTree($featuresDir, 3);
            if ($featTree !== '') {
                $sections[] = "## Feature files\n$featTree";
            }
        }

        $stepsDir = $this->resolveFeaturePaths()['steps'];
        $stepSignatures = $this->scanStepSignatures(getcwd() . '/' . $stepsDir);
        if ($stepSignatures !== '') {
            $sections[] = "## Existing step definitions\nThese steps are already defined — reuse them in new scenarios:\n$stepSignatures";
        }

        if (empty($sections)) {
            return '';
        }

        return "# Project file tree\n\n" . implode("\n\n", $sections);
    }

    /**
     * Recursively scans a directory tree up to a given depth, returning an indented listing.
     */
    private function scanTree(string $absPath, int $maxDepth, int $depth = 0): string
    {
        if ($depth >= $maxDepth || !$this->filesystem->exists($absPath) || !$this->filesystem->isDir($absPath)) {
            return '';
        }

        $entries = $this->filesystem->scandir($absPath);
        $entries = array_filter($entries, fn($e) => $e !== '.' && $e !== '..');
        sort($entries);

        $lines = [];
        $indent = str_repeat('  ', $depth);

        foreach ($entries as $entry) {
            $full = $absPath . '/' . $entry;
            if ($this->filesystem->isDir($full)) {
                $lines[] = "$indent$entry/";
                $sub = $this->scanTree($full, $maxDepth, $depth + 1);
                if ($sub !== '') {
                    $lines[] = $sub;
                }
            } else {
                $lines[] = "$indent$entry";
            }
        }

        return implode("\n", $lines);
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

    private function formatToolDisplay(ToolCall $toolCall): string
    {
        $names = [
            'read_file' => ['Read', 'path'],
            'list_files' => ['List', 'directory'],
            'write_file' => ['Write', 'path'],
            'update_file' => ['Update', 'path'],
            'describe' => ['Describe', 'class_name'],
            'add_example' => ['Add example', 'class_name'],
            'generate_feature' => ['Generate feature', 'feature_name'],
            'generate_steps' => ['Generate steps', 'feature_name'],
            'run_specs' => ['Run', 'path'],
            'inspect_symbol' => ['Inspect', 'fqcn'],
        ];

        $info = $names[$toolCall->name] ?? null;
        if ($info === null) {
            return "  \u{23FA} <options=bold>$toolCall->name</>";
        }

        [$displayName, $argKey] = $info;
        $mainArg = $toolCall->arguments[$argKey] ?? '';
        if ($mainArg === '' && $toolCall->name === 'run_specs') {
            $mainArg = 'all';
        }

        $argDisplay = $mainArg !== '' ? "($mainArg)" : '';
        return "  \u{23FA} <options=bold>$displayName</>$argDisplay";
    }

}
