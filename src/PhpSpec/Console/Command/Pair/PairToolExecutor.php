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

use Closure;
use PhpSpec\Ai\Agent\CommandProfile;
use PhpSpec\Ai\Agent\ProjectPath;
use PhpSpec\Ai\Agent\Proposal;
use PhpSpec\Ai\Agent\ToolRegistry;
use PhpSpec\Ai\Agent\Writer;
use PhpSpec\Ai\AiTools;
use PhpSpec\Ai\Contracts\ToolExecutor;
use PhpSpec\Ai\Contracts\ToolInterface;
use PhpSpec\Ai\PromptLibrary;
use PhpSpec\Ai\Response;
use PhpSpec\Ai\SymbolInspector;
use PhpSpec\Ai\ToolCall;
use PhpSpec\CodeGeneration\LegacySpecDetector;
use PhpSpec\CodeGeneration\SpecGenerator;
use PhpSpec\Configuration;
use PhpSpec\Extensions\ExtensionLoader;
use PhpSpec\Filesystem;
use PhpSpec\StoryBDD\StepVocabulary;
use RuntimeException;
use Throwable;

/**
 * @internal
 * The pair session's half of the agent loop: owns the role tool surfaces (from
 * the navigator/driver manifests through the registry), executes tool calls
 * live against the session (auto-refusals, chooser confirm gates, the shared
 * Writer, auto-verify), and settles each turn (one artifact while driving, one
 * offer while navigating, the driver's hand-back).
 */
final class PairToolExecutor implements ToolExecutor
{
    private const WRITE_TOOLS = ['describe', 'add_example', 'generate_feature', 'generate_steps', 'write_file', 'update_file'];

    /**
     * How many tool rounds a driving AI gets after its one artifact (enough to
     * run the spec and report) before the turn hands back to the human.
     */
    private const DRIVER_WRAPUP_ROUNDS = 4;

    /** Serves the tool schemas and descriptions the role manifests declare. */
    private readonly ToolRegistry $registry;

    /** @var array<string, ToolInterface> */
    private array $tools = [];

    private bool $initialised = false;

    /**
     * Each role's manifest-declared tool names, keyed by command name, so
     * advertising filters to the current role's declared surface.
     *
     * @var array<string, list<string>>
     */
    private array $roleTools = [];

    /**
     * Extension-provided tool names: outside any manifest, always advertised.
     *
     * @var list<string>
     */
    private array $extensionToolNames = [];

    /** Whether the AI has written its one artifact this turn (while driving). */
    private bool $artifactWrittenThisHandle = false;

    /** The artifact flag as it stood when the round started, for settling. */
    private bool $hadArtifactAtRoundStart = false;

    /**
     * Whether this turn's auto-verify must run the whole suite (--all): a
     * spec, or source the specs exercise, is verified by the spec run alone,
     * but a feature or steps change (or any other artifact) can only be
     * verified by running the stories too.
     */
    private bool $verifyWholeSuite = false;

    /**
     * The structured next-step suggestion the model registered this turn via
     * suggest_next, for the dispatcher to turn into a ghost prompt.
     *
     * @var array<string, string>|null
     */
    private ?array $lastSuggestion = null;

    /**
     * Whether an offer was resolved (accepted OR declined) this turn: the
     * navigator gets ONE offer per turn, mirroring the driver's one artifact,
     * so a turn always winds down into prose instead of chaining offers.
     */
    private bool $offerResolvedThisHandle = false;

    /** Tool rounds taken since the driving AI wrote its artifact this turn. */
    private int $postArtifactRounds = 0;

    public function __construct(
        private readonly Configuration $config,
        private readonly Filesystem $filesystem,
        private readonly PairOutput $output,
        private readonly Chooser $chooser,
        private readonly RoleState $roleState,
        private readonly SpecRunner $specRunner,
        private readonly PromptLibrary $prompts,
        private readonly ?ExtensionLoader $extensionLoader = null,
    ) {
        $this->registry = new ToolRegistry($config, $filesystem, $prompts);
    }

    /**
     * Resets the per-turn state before a user turn's first round.
     */
    public function beginTurn(): void
    {
        $this->ensureInitialised();
        $this->artifactWrittenThisHandle = false;
        $this->hadArtifactAtRoundStart = false;
        $this->verifyWholeSuite = false;
        $this->postArtifactRounds = 0;
        $this->lastSuggestion = null;
        $this->offerResolvedThisHandle = false;
    }

    /**
     * The tools advertised to the model this round, serialised for the
     * provider. A round starts here, so the artifact flag is snapshotted for
     * the round's settling.
     *
     * @return list<array{name: string, description: string, input_schema: array<string, mixed>}>
     */
    public function advertised(): array
    {
        $this->ensureInitialised();
        $this->hadArtifactAtRoundStart = $this->artifactWrittenThisHandle;
        $role = $this->roleState->current();

        // The role's manifest declares its static tool surface (a navigator
        // has no write tools to withhold; a driver has no offer_change), so
        // the static filter is data. Extension tools sit outside any manifest
        // and are always advertised.
        $declared = $this->roleTools[$role->commandName()] ?? [];
        $tools = array_values(array_filter(
            $this->tools,
            fn(ToolInterface $tool) => in_array($tool->getName(), $declared, true)
                || in_array($tool->getName(), $this->extensionToolNames, true),
        ));

        // While the AI drives, once it has written its one artifact this turn
        // the write tools are withheld, so it can only read and run for the
        // rest of the turn.
        if ($role->aiIsDriver() && $this->artifactWrittenThisHandle) {
            $tools = array_values(array_filter(
                $tools,
                fn(ToolInterface $tool) => !in_array($tool->getName(), self::WRITE_TOOLS, true),
            ));
        }

        // An offer happens at most once per turn; likewise one registered
        // suggestion per turn. Withholding the used-up tool is what lets the
        // loop end in prose instead of chaining suggestion after suggestion.
        $spent = [];
        if ($this->offerResolvedThisHandle) {
            $spent[] = 'offer_change';
        }
        // A verified change ends the turn: no fresh suggestion rides on its
        // back; the human asks for the next step.
        if ($this->lastSuggestion !== null || $this->artifactWrittenThisHandle) {
            $spent[] = 'suggest_next';
        }
        if ($spent !== []) {
            $tools = array_values(array_filter(
                $tools,
                fn(ToolInterface $tool) => !in_array($tool->getName(), $spent, true),
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

    public function execute(ToolCall $toolCall): mixed
    {
        $this->ensureInitialised();
        $tool = $this->tools[$toolCall->name] ?? null;

        if ($tool === null) {
            return ['error' => "Unknown tool: $toolCall->name"];
        }

        $isWrite = in_array($toolCall->name, self::WRITE_TOOLS, true);

        // Auto-refuse a write the role or one-artifact rule forbids: before it
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
        // Either answer may carry a typed note (Tab on the chooser), which is
        // handed to the model as part of the outcome.
        if ($isWrite && !$this->chooser->choose($this->confirmQuestion($toolCall), 'write-files', 'apply file changes')) {
            PairLogger::log('RESULT', 'User declined');

            return self::declineSteer($this->chooser->lastNote());
        }

        $note = $isWrite ? $this->chooser->lastNote() : '';

        try {
            $result = $tool->execute($toolCall->arguments);

            // A write that came back with an error (e.g. a rejected legacy-style
            // spec) did not land, so it does not count as the turn's artifact ,
            // the model may retry it in the correct form this same turn.
            if ($isWrite && !(is_array($result) && isset($result['error']))) {
                $path = $toolCall->arguments['path'] ?? null;
                $this->noteArtifact(is_string($path) ? $path : null);

                if ($note !== '' && is_string($result)) {
                    $result .= sprintf(' The human added: "%s".', $note);
                }
            }

            PairLogger::log('RESULT', is_string($result) ? $result : (json_encode($result) ?: ''));

            return $result;
        } catch (Throwable $e) {
            PairLogger::log('RESULT', "ERROR: {$e->getMessage()}");

            return ['error' => $e->getMessage()];
        }
    }

    /**
     * After the AI's one artifact lands this round, runs the suite and reports
     * the fresh red/green, so the model learns the outcome of its own change
     * without having to choose to run. The run is a read: it never consumes
     * the one-artifact budget: and only fires the round the artifact is
     * written (not on later rounds of the same turn).
     *
     * @return list<string>
     */
    public function observations(): array
    {
        if ($this->hadArtifactAtRoundStart || !$this->artifactWrittenThisHandle) {
            return [];
        }

        $this->output->getOutput()->writeln('  <fg=gray>Verifying your change...</>');

        $outcome = $this->specRunner->run($this->verifyWholeSuite ? '--all' : '', $this->output->getOutput());

        if ($outcome === null) {
            return [];
        }

        $report = SituationReport::fromOutcome($outcome, $this->roleState->current());

        return ["[Auto-verify after your change]\n" . $report->render()];
    }

    /**
     * Whether a driving AI's turn should hand back now. A driver takes one
     * artifact, then runs and reports: so once the artifact is written the turn
     * ends as soon as it reaches for another write (it is overreaching the goal),
     * or once it has used up its brief wrap-up window. Never applies while the AI
     * is navigating (the human's turn runs freely).
     */
    public function turnComplete(Response $response): ?string
    {
        if (!$this->roleState->current()->aiIsDriver() || !$this->artifactWrittenThisHandle) {
            return null;
        }

        if ($this->hadArtifactAtRoundStart && $this->attemptsWrite($response)) {
            return self::driverHandBack();
        }

        if (++$this->postArtifactRounds >= self::DRIVER_WRAPUP_ROUNDS) {
            return self::driverHandBack();
        }

        return null;
    }

    /**
     * The structured suggestion the model registered this turn (via the
     * suggest_next tool), or null when it registered none.
     *
     * @return array<string, string>|null
     */
    public function lastSuggestion(): ?array
    {
        return $this->lastSuggestion;
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
    private static function driverHandBack(): string
    {
        return 'That\'s my one change for this turn. /run it, tell me the next step, or /swap to take the keyboard back.';
    }

    /**
     * Records the artifact that landed this turn and widens the auto-verify
     * scope when the spec run alone cannot vouch for it. A null path means a
     * spec generator wrote it (describe, add_example), which specs verify.
     */
    private function noteArtifact(?string $path): void
    {
        $this->artifactWrittenThisHandle = true;

        if ($path !== null && !self::specsCanVerify($path)) {
            $this->verifyWholeSuite = true;
        }
    }

    /**
     * Whether the spec run alone verifies an artifact at this path: a spec, or
     * source code the specs exercise. Features, steps files, and anything else
     * need the whole suite.
     */
    private static function specsCanVerify(string $path): bool
    {
        if (str_ends_with($path, '.steps.php') || str_ends_with($path, '.feature')) {
            return false;
        }

        return str_ends_with($path, '.php');
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
     * the confirm step: it steers back into the cycle (re-clarify, don't retry),
     * carrying the typed note as the direction to take when one was given.
     *
     * @param string $note the note typed alongside the decline, empty when none
     * @return array{error: string}
     */
    private static function declineSteer(string $note): array
    {
        if ($note !== '') {
            return ['error' => sprintf('You declined this step, saying: "%s". Address that instead; '
                . 'don\'t repeat the same write. Or /swap to take the keyboard back.', $note)];
        }

        return ['error' => 'You declined this step. Don\'t repeat the same write, ask what I should '
            . 'change instead, then re-plan. Or /swap to take the keyboard back.'];
    }

    /**
     * Refuses a write that the role or the one-artifact rule forbids, returning
     * the error payload to send back to the model: the AI never writes while
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
    }

    /**
     * Builds the session's tool surface from the role manifests: each role's
     * `commands/<name>.txt` declares its tools, the registry serves their
     * schemas and editable descriptions, and pair binds its live handlers. The
     * union of both roles is built once; advertising filters per round.
     *
     * @return ToolInterface[]
     */
    private function buildTools(): array
    {
        $handlers = $this->handlers();
        $byName = [];

        foreach ([PairRole::HumanDrives, PairRole::AiDrives] as $role) {
            $profile = $this->roleProfile($role);
            $this->roleTools[$role->commandName()] = $profile->tools;

            foreach ($this->registry->definitions($profile, $handlers) as $tool) {
                $byName[$tool->getName()] ??= $tool;
            }
        }

        if ($this->extensionLoader !== null) {
            foreach ($this->extensionLoader->getToolProviders() as $provider) {
                foreach ($provider->getTools() as $tool) {
                    $byName[$tool->getName()] = $tool;
                    $this->extensionToolNames[] = $tool->getName();
                }
            }
        }

        return array_values($byName);
    }

    /**
     * The role's command profile, its manifest layers resolved through the
     * prompt library (project overrides first).
     */
    private function roleProfile(PairRole $role): CommandProfile
    {
        return CommandProfile::compose($role->commandName(), ...$this->prompts->stack('commands/' . $role->commandName()));
    }

    /**
     * Pair's live handlers, keyed by tool name. The registry serves what a tool
     * IS (schema, description); these closures are what it DOES in a session:
     * generate, write through the confirm gate, run, inspect, ask.
     *
     * @return array<string, Closure>
     */
    private function handlers(): array
    {
        return [
            'describe' => $this->describeHandler(),
            'add_example' => $this->addExampleHandler(),
            'generate_feature' => $this->generateFeatureHandler(),
            'generate_steps' => $this->generateStepsHandler(),
            'write_file' => $this->writeFileHandler(),
            'update_file' => $this->updateFileHandler(),
            'offer_change' => $this->offerChangeHandler(),
            'suggest_next' => $this->suggestNextHandler(),
            'ask_user' => $this->askUserHandler(),
            'run_specs' => $this->runSpecsHandler(),
            'inspect_symbol' => $this->inspectSymbolHandler(),
            'read_file' => self::executes(AiTools::readFile($this->filesystem)),
            'list_files' => self::executes(AiTools::listFiles($this->filesystem)),
        ];
    }

    /**
     * A handler that delegates to an existing tool's implementation, so shared
     * tools (read_file, list_files) are defined once and executed here.
     */
    private static function executes(ToolInterface $tool): Closure
    {
        return static fn(array $arguments) => $tool->execute($arguments);
    }

    private function askUserHandler(): Closure
    {
        $chooser = $this->chooser;

        return function (array $args) use ($chooser) {
            $kind = 'ai-' . preg_replace('/[^a-z0-9]+/', '-', strtolower($args['action']));

            $accepted = $chooser->choose($args['question'], $kind, $args['action']);
            $answer = !$accepted ? 'no' : ($chooser->hasAlways($kind) ? 'always' : 'yes');
            $note = $chooser->lastNote();

            if ($note !== '') {
                return sprintf('%s. The human added: "%s".', $answer, $note);
            }

            return $answer;
        };
    }

    /**
     * A proposal for an absolute path and content, reading any existing file so
     * the diff shown is real. Tools only ever produce these; nothing writes
     * except the gate below. Steps content is checked against the project's
     * step vocabulary first: a title registers once across all steps files,
     * so a duplicate is rejected here instead of erroring at the next load.
     */
    private function proposalFor(string $absPath, string $content, string $origin): Proposal
    {
        if (str_ends_with($absPath, '.steps.php')) {
            $root = getcwd() . '/' . trim($this->config->getFeaturesPath(), './');
            $rejection = (new StepVocabulary($this->filesystem))->rejectionFor($content, $absPath, $root);
            if ($rejection !== null) {
                throw new RuntimeException($rejection);
            }
        }

        $exists = $this->filesystem->exists($absPath);

        return new Proposal(ProjectPath::relative($absPath), $exists ? $this->filesystem->read($absPath) : '', $content, !$exists, $origin);
    }

    /**
     * Pair's single write gate: applies a confirmed proposal through the shared
     * Writer, then shows a diff when the file already existed or a full listing
     * when it is new, so an overwrite is never rendered as an all-new file.
     */
    private function applyProposal(Proposal $proposal): void
    {
        (new Writer($this->filesystem))->apply($proposal);

        $absPath = (getcwd() ?: '.') . '/' . $proposal->path;
        if ($proposal->isNew) {
            $this->output->fileDisplay($absPath, $proposal->new, true);
        } else {
            $this->output->fileDiff($absPath, $proposal->old, $proposal->new);
        }
    }

    private function describeHandler(): Closure
    {
        $specPath = ltrim($this->config->getSpecPath(), './');
        $specSuffix = $this->config->getSpecSuffix();
        $filesystem = $this->filesystem;
        $output = $this->output;

        return function (array $args) use ($specPath, $specSuffix, $filesystem, $output) {
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
            $this->applyProposal($this->proposalFor($filePath, $generator->skeleton($classPath), 'describe'));

            return "Spec skeleton for $classPath created at $filePath. Add behaviour with add_example.";
        };
    }

    private function addExampleHandler(): Closure
    {
        $specPath = ltrim($this->config->getSpecPath(), './');
        $specSuffix = $this->config->getSpecSuffix();
        $filesystem = $this->filesystem;
        $output = $this->output;

        return function (array $args) use ($specPath, $specSuffix, $filesystem, $output) {
            $classPath = $args['class_name'];
            $method = $args['method'];

            $filePath = getcwd() . DIRECTORY_SEPARATOR
                . $specPath . DIRECTORY_SEPARATOR
                . str_replace('/', DIRECTORY_SEPARATOR, $classPath)
                . $specSuffix;

            $generator = new SpecGenerator($specPath, $filesystem, $specSuffix);

            $existed = $filesystem->exists($filePath);
            $before = $existed ? $filesystem->read($filePath) : $generator->skeleton($classPath);
            $grown = $generator->withExample($before, $classPath, $method);

            if ($grown === null && $existed) {
                $output->getOutput()->writeln(sprintf(
                    '  <fg=gray>An example for %s::%s already exists.</>',
                    str_replace('/', '\\', $classPath),
                    $method,
                ));

                return "Example for $classPath::$method already exists; no change made.";
            }

            if ($grown === null) {
                return ['error' => "Could not add an example for $classPath::$method."];
            }

            $this->applyProposal($this->proposalFor($filePath, $grown, 'add_example'));

            return "Example for $classPath::$method added to $filePath.";
        };
    }

    private function generateFeatureHandler(): Closure
    {
        $featuresPath = $this->resolveFeaturePaths()['features'];

        return function (array $args) use ($featuresPath) {
            $filePath = getcwd() . '/' . $featuresPath . '/' . $args['feature_name'] . '.feature';

            $this->applyProposal($this->proposalFor($filePath, $args['content'], 'generate_feature'));

            return "Feature file written to $filePath";
        };
    }

    private function generateStepsHandler(): Closure
    {
        $stepsPath = $this->resolveFeaturePaths()['steps'];

        return function (array $args) use ($stepsPath) {
            $filePath = getcwd() . '/' . $stepsPath . '/' . $args['feature_name'] . '.steps.php';

            $this->applyProposal($this->proposalFor($filePath, $args['content'], 'generate_steps'));

            return "Steps file written to $filePath";
        };
    }

    private function writeFileHandler(): Closure
    {
        $filesystem = $this->filesystem;
        $specDir = $this->specDir();

        return function (array $args) use ($filesystem, $specDir) {
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

            $this->applyProposal($this->proposalFor($absPath, $content, 'write_file'));

            return "File written to $absPath";
        };
    }

    private function updateFileHandler(): Closure
    {
        $filesystem = $this->filesystem;
        $specDir = $this->specDir();

        return function (array $args) use ($filesystem, $specDir) {
            $path = $args['path'];
            $content = $args['content'];
            $absPath = getcwd() . '/' . ltrim($path, '/');

            if (!$filesystem->exists($absPath)) {
                return "File not found: $path. Use write_file to create it.";
            }

            $rejection = self::specWriteRejection($absPath, $specDir, $content, $filesystem->read($absPath));
            if ($rejection !== null) {
                return $rejection;
            }

            $this->applyProposal($this->proposalFor($absPath, $content, 'update_file'));

            return "File updated: $absPath";
        };
    }

    /**
     * The navigator's channel for concrete advice: an offered change is shown
     * to the human as a diff FIRST, then accepted or declined through the
     * chooser, and only an accepted offer reaches disk (through the shared
     * Writer). The spec guards apply to offers exactly as to writes.
     */
    private function offerChangeHandler(): Closure
    {
        $filesystem = $this->filesystem;
        $specDir = $this->specDir();

        return function (array $args) use ($filesystem, $specDir) {
            if ($this->offerResolvedThisHandle) {
                return ['error' => 'One offer per turn. React to its outcome in prose, or wait for the human.'];
            }

            $path = $args['path'];
            $content = $args['content'];
            $absPath = getcwd() . '/' . ltrim($path, '/');

            $rejection = self::specWriteRejection($absPath, $specDir, $content, $filesystem->exists($absPath) ? $filesystem->read($absPath) : '');
            if ($rejection !== null) {
                return $rejection;
            }

            $proposal = $this->proposalFor($absPath, $content, 'offer_change');

            // The diff IS the offer: the human sees it before deciding.
            if ($proposal->isNew) {
                $this->output->fileDisplay($absPath, $proposal->new, true);
            } else {
                $this->output->fileDiff($absPath, $proposal->old, $proposal->new);
            }

            $this->offerResolvedThisHandle = true;

            if (!$this->chooser->choose($this->offerQuestion($args), 'offer-change', 'apply offered changes')) {
                PairLogger::log('RESULT', 'Offer declined');

                return self::offerDeclined($this->chooser->lastNote());
            }

            $note = $this->chooser->lastNote();

            (new Writer($filesystem))->apply($proposal);
            $this->noteArtifact($path);

            if ($note !== '') {
                return sprintf('Change applied to %s. The human added: "%s".', $absPath, $note);
            }

            return "Change applied to $absPath.";
        };
    }

    /**
     * The offer's confirm prompt: the stated intent so the human decides on the
     * plan, not just the mechanics.
     *
     * @param array<string, mixed> $args
     */
    private function offerQuestion(array $args): string
    {
        $intent = $args['intent'] ?? null;

        if (is_string($intent) && trim($intent) !== '') {
            return 'Plan: ' . trim($intent) . "\n  Apply this change?";
        }

        return 'Apply this offered change?';
    }

    /**
     * The reply handed back when the human declines an offer: re-plan, never
     * re-offer, and when the decline carried a typed note, that note is the
     * direction to take instead.
     *
     * @param string $note the note typed alongside the decline, empty when none
     * @return array{error: string}
     */
    private static function offerDeclined(string $note): array
    {
        if ($note !== '') {
            return ['error' => sprintf('The human declined this offer, saying: "%s". Address that '
                . 'instead; do not re-offer the same change.', $note)];
        }

        return ['error' => 'The human declined this offer. Ask what they would prefer or refine the '
            . 'suggestion; do not re-offer the same change.'];
    }

    /**
     * Registers the model's structured next-step suggestion, so the dispatcher
     * can pre-fill the prompt with a matching /generate ghost.
     */
    private function suggestNextHandler(): Closure
    {
        return function (array $args): string|array {
            if ($this->artifactWrittenThisHandle) {
                return ['error' => 'The change just landed and was verified. Report the outcome and hand back; the human will ask for the next step.'];
            }
            if ($this->lastSuggestion !== null) {
                return ['error' => 'One suggestion per turn is already registered. Advise in prose now.'];
            }

            $this->lastSuggestion = array_map(strval(...), array_filter($args, is_scalar(...)));

            return 'Suggestion noted. Keep advising in prose; never repeat it as JSON.';
        };
    }

    private function runSpecsHandler(): Closure
    {
        $output = $this->output;
        $specRunner = $this->specRunner;
        $roleState = $this->roleState;

        return function (array $args) use ($output, $specRunner, $roleState) {
            $path = $args['path'] ?? '';

            $output->getOutput()->writeln('  <fg=gray>Running specs...</>');

            $outcome = $specRunner->run($path, $output->getOutput());

            return SituationReport::fromOutcome($outcome, $roleState->current())->render();
        };
    }

    private function inspectSymbolHandler(): Closure
    {
        $inspector = new SymbolInspector(
            ltrim($this->config->getSrcPath(), './'),
            $this->config->getPsr4Prefix(),
            $this->filesystem,
        );

        return fn(array $args) => $inspector->describe($args['fqcn']);
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
     * spec directory: the path arbitrary content can otherwise take around the
     * spec tools. Rejects phpspec 8 syntax and any rewrite that would drop
     * existing examples, so both bypasses of the describe/add_example surface are
     * closed. Returns the rejection payload, or null when the write may proceed.
     *
     * @return array{error: string}|null
     */
    private static function specWriteRejection(string $absPath, string $specDir, string $newContent, string $oldContent): ?array
    {
        // The write path is built with "/" while the spec dir uses the platform
        // separator, so normalise both before comparing: otherwise the guard
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
