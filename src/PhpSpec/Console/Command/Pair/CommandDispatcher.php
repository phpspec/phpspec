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

use Exception;
use PhpSpec\Ai\Agent\Agent;
use PhpSpec\Ai\Agent\Writer;
use PhpSpec\Ai\PromptLibrary;
use PhpSpec\Ai\ProviderFactory;
use PhpSpec\CodeGeneration\ClassGenerator;
use PhpSpec\CodeGeneration\ClassLocation;
use PhpSpec\CodeGeneration\SpecGenerator;
use PhpSpec\Configuration;
use PhpSpec\Console\Command\Pair;
use PhpSpec\Console\Command\Run\CodeGenerator;
use PhpSpec\Console\Command\Run\GenerationCandidates;
use PhpSpec\Console\Command\Run\RecencyScanner;
use PhpSpec\Console\Command\Run\SuiteSummary;
use PhpSpec\Extensions\ExtensionLoader;
use PhpSpec\Filesystem;
use PhpSpec\RealFilesystem;
use RuntimeException;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * @internal
 * Maps parsed REPL input to service calls for the pair programming mode.
 */
final class CommandDispatcher
{
    public const CONTINUE = 0;
    public const QUIT = 1;

    private InputParser $parser;
    private Filesystem $filesystem;
    private readonly Chooser $chooser;
    private readonly SpecRunner $specRunner;
    private readonly RoleState $roleState;
    private readonly Agent $agent;

    /** Resolves prompt files, project overrides first. */
    private readonly PromptLibrary $prompts;
    private ?AiAssistant $ai = null;

    /**
     * Why a configured AI provider failed to start, captured at construction so
     * it can be shown when the human actually reaches for the AI — instead of
     * being printed once at startup and buried by the scroll region. Null when
     * AI is ready or was never configured.
     */
    private ?string $aiUnavailableReason = null;

    /**
     * The most recent suite summary, remembered so every AI turn — including a
     * freeform one that did not itself run the specs — is grounded in the same
     * red/green state the human just saw.
     */
    private ?SuiteSummary $lastSituation = null;

    /**
     * The command to pre-fill the next prompt with as a dim ghost suggestion —
     * the natural next step after the command just run (e.g. run the spec you
     * just described). Null when there is nothing obvious to suggest.
     */
    private ?string $suggestion = null;

    /**
     * @param SpecGenerator $specGenerator the spec file generator
     * @param ClassGenerator $classGenerator the class file generator
     * @param Configuration $config the project configuration
     * @param PairOutput $output the pair-mode output helper
     * @param bool $interactive whether to prompt for user input (false = auto-accept all)
     * @param Filesystem|null $filesystem filesystem abstraction for testability
     * @param Application|null $application the Symfony console application for command delegation
     * @param SpecRunner|null $specRunner runs specs for `run`; defaults to a fresh subprocess
     */
    public function __construct(
        private readonly SpecGenerator $specGenerator,
        private readonly ClassGenerator $classGenerator,
        private readonly Configuration $config,
        private readonly PairOutput $output,
        private readonly bool $interactive = true,
        ?Filesystem $filesystem = null,
        private readonly ?Application $application = null,
        private readonly ?ExtensionLoader $extensionLoader = null,
        ?Chooser $chooser = null,
        ?SpecRunner $specRunner = null,
        ?RoleState $roleState = null,
        ?Agent $agent = null,
        ?AiAssistant $ai = null,
    ) {
        $this->parser = new InputParser();
        $this->filesystem = $filesystem ?? new RealFilesystem();
        $this->prompts = new PromptLibrary($this->filesystem);
        $this->chooser = $chooser ?? new Chooser($output, $interactive);
        $this->specRunner = $specRunner ?? new SubprocessRunner();
        $this->roleState = $roleState ?? new RoleState();
        $this->agent = $agent ?? new Agent($this->config, $this->filesystem);
        $this->registerAutoloader();

        if ($ai !== null) {
            $this->ai = $ai;

            return;
        }

        $aiConfig = $this->config->getAiConfig();
        if ($aiConfig !== null) {
            try {
                $provider = ProviderFactory::create($aiConfig);
                $model = $aiConfig['model'] ?? ProviderFactory::defaultModel($aiConfig['provider']);
                $this->ai = new AiAssistant($provider, $this->config, $this->output, $model, $this->filesystem, $this->interactive, $this->extensionLoader, $this->chooser, $this->roleState);
            } catch (Exception $e) {
                // Any provider failure — an unknown name, a missing package, a bad
                // key — leaves AI unavailable rather than crashing the session.
                $this->aiUnavailableReason = $e->getMessage();
            }
        }
    }

    /**
     * Whether the AI assistant actually started: an "ai" block was configured
     * and its provider was constructed. False both when no AI is configured and
     * when a configured provider failed to load — {@see aiUnavailableReason()}
     * tells the two apart.
     */
    public function aiIsReady(): bool
    {
        return $this->ai !== null;
    }

    /**
     * The reason a configured AI provider failed to start, or null when AI is
     * ready or was never configured.
     */
    public function aiUnavailableReason(): ?string
    {
        return $this->aiUnavailableReason;
    }

    /**
     * Opens the session with an observation drawn from the current suite state,
     * in place of a static menu. Runs the suite once, quietly.
     */
    public function greet(): void
    {
        (new Greeter($this->specRunner, $this->output, $this->ai !== null))->greet();
    }

    /**
     * Swaps who holds the keyboard and announces the new contract. Roles are an
     * AI concept — without a provider there is no navigator or driver to swap
     * between, so we say so instead of pretending.
     */
    private function handleSwap(): int
    {
        $console = $this->output->getOutput();
        $console->writeln('');

        if ($this->ai === null) {
            $console->writeln('  <fg=gray>Swapping needs an AI provider — I can only navigate or drive when one is configured. See /help.</>');

            return self::CONTINUE;
        }

        $role = $this->roleState->swap();
        $this->ai->reloadPrompt();

        $console->writeln('  <fg=cyan>' . $role->contractLine() . '</>');

        return self::CONTINUE;
    }

    /**
     * Suggests the single next step from a real suite run, following outside-in,
     * feature-first TDD. Features run too (--all), so a red or unwritten scenario
     * leads; when features are green it points at a baby step over the last-touched
     * files. With AI it hands the shared outside-in coaching to the assistant (the
     * navigator advises, the driver takes the step); without AI it prints the
     * deterministic, feature-aware narrator.
     */
    private function handleNext(): int
    {
        // --all runs features too, so the advice can favour them. A fake SpecRunner
        // in specs ignores the argument; SubprocessRunner appends it to the child
        // argv (an empty argument would mean specs only).
        $outcome = $this->specRunner->run('--all', new BufferedOutput());
        $this->lastSituation = $outcome->summary ?? $this->lastSituation;

        if ($this->ai !== null) {
            $this->ai->handle($this->nextInstruction(), $this->lastSituation);

            // A registered suggestion becomes a ghost-prefilled /generate, so
            // acting on the advice is Tab + Enter, never retyping it.
            $ghost = self::generateGhost($this->ai->lastSuggestion());
            if ($ghost !== null) {
                $this->suggestion = $ghost;
            }

            return self::CONTINUE;
        }

        $recency = new RecencyScanner($this->filesystem);
        $cwd = getcwd() ?: '.';
        $recentFeature = $recency->mostRecentFeature($cwd . '/' . trim($this->config->getFeaturesPath(), './'));
        $recentSource = $recency->mostRecentSource($cwd . '/' . ltrim($this->config->getSrcPath(), './'));

        $next = (new SuiteNarrator())->next($outcome, $this->roleState->current(), $recentFeature, $recentSource);

        $console = $this->output->getOutput();
        foreach ($next['lines'] as $line) {
            $console->writeln($line);
        }

        return self::CONTINUE;
    }

    /**
     * The `next` coaching handed to the AI: the shared outside-in prompt file plus
     * a short ask. The role in force (navigator advises, driver executes) is already
     * encoded in the AI's cached system prompt, so the ask stays role-agnostic.
     */
    private function nextInstruction(): string
    {
        $coaching = $this->prompts->read('next');
        if (trim($coaching) === '') {
            $coaching = 'Follow outside-in, feature-first TDD — favour feature (story) tests, always a baby step.';
        }

        return $coaching . "\n\n" . 'Based on our current suite state, name and take the single next baby step now: one artifact, then hand back. Register the step with suggest_next first, then advise in prose.';
    }

    /**
     * The /generate ghost a registered suggestion maps to, phrased so the
     * one-shot's step resolution routes it (feature/spec wording, a class to
     * resolve), or null for suggestions with nothing concrete to prefill.
     *
     * @param array<string, string>|null $suggestion
     */
    private static function generateGhost(?array $suggestion): ?string
    {
        $target = trim($suggestion['target'] ?? '');
        if ($target === '') {
            return null;
        }

        return match ($suggestion['type'] ?? '') {
            'feature' => '/generate a feature for ' . $target,
            'spec' => '/generate a spec for ' . $target,
            'example' => '/generate add a spec example for ' . $target,
            'refactor' => '/refactor ' . $target,
            default => null,
        };
    }

    /**
     * Dispatches a raw input string to the appropriate handler.
     *
     * @param string $input the raw user input
     * @return int CONTINUE to keep the REPL running, QUIT to exit
     */
    public function dispatch(string $input): int
    {
        PairLogger::log('CMD', $input);
        $parsed = $this->parser->parse($input);
        $command = $parsed['command'];

        if ($command === '') {
            return self::CONTINUE;
        }

        // A leading slash always marks a command, and a bare line reaches these
        // arms only when the parser routed it as one: its first word read
        // unambiguously as a command (see InputParser::route()). Every other
        // line is a prompt for the AI (or the unknown-command hint when no AI
        // is configured).
        if (!str_starts_with($command, '/')) {
            $this->suggestion = null;

            return $this->handleAi($input);
        }

        $result = match ($command) {
            '/describe' => $this->handleDescribe($parsed['argument']),
            '/exemplify' => $this->handleExemplify($parsed['argument']),
            '/run' => $this->handleRun($this->runArgument($parsed['tail'])),
            '/next' => $this->handleNext(),
            '/generate' => $this->handleGenerate($parsed['argument']),
            '/clear' => $this->handleClear(),
            '/swap' => $this->handleSwap(),
            '/help' => $this->handleHelp(),
            '/quit', '/exit' => self::QUIT,
            default => $this->handleSlashCommand($command, $parsed['tail']),
        };

        // Set from the top-level command only, so a nested run (e.g. the "run
        // now?" step of /describe) never clobbers the hint.
        // The static follow-up table never overrides a ghost the handler itself
        // set (e.g. /next prefilling a /generate from the AI's suggestion).
        $this->suggestion = $this->suggestionFor($command, $parsed['argument']) ?? $this->suggestion;

        return $result;
    }

    /**
     * The command to pre-fill the next prompt with, chosen from what was just
     * run: describe a spec then run it, run then ask what's next, and so on.
     */
    public function suggestion(): ?string
    {
        return $this->suggestion;
    }

    /**
     * The natural next command after the given one, or null when there is no
     * obvious follow-up to ghost into the prompt.
     */
    private function suggestionFor(string $command, string $argument): ?string
    {
        return match ($command) {
            '/describe' => $argument !== '' ? '/run ' . $this->specPathFor($argument) : null,
            '/exemplify', '/generate' => '/run',
            '/run' => '/next',
            default => null,
        };
    }

    /**
     * The project-relative spec path for a described class argument.
     */
    private function specPathFor(string $argument): string
    {
        $spec = str_replace('\\', '/', $argument);

        return $this->specGenerator->getSpecPath() . '/' . $spec . $this->specGenerator->getSpecSuffix();
    }

    /**
     * Generates a spec file for the given class, then offers to create the class.
     */
    private function handleDescribe(string $argument): int
    {
        if ($argument === '') {
            $this->output->error('Usage: /describe Acme\Greeter');
            return self::CONTINUE;
        }

        $fqcn = str_replace('/', '\\', $argument);
        $spec = str_replace('\\', '/', $fqcn);

        $specPath = $this->specGenerator->getSpecPath();
        $specFile = getcwd() . DIRECTORY_SEPARATOR
            . $specPath . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $spec)
            . $this->specGenerator->getSpecSuffix();

        // Generate spec
        $this->output->getOutput()->writeln('');
        if ($this->filesystem->exists($specFile)) {
            $this->output->getOutput()->writeln("  <fg=gray>Spec already exists: $specFile</>");
        } else {
            try {
                $this->specGenerator->generate($spec);
            } catch (RuntimeException $e) {
                $this->output->error($e->getMessage());
                return self::CONTINUE;
            }

            $this->output->getOutput()->writeln(sprintf(
                '  Specification for <fg=bright-blue>%s</> created in <fg=cyan>%s</>',
                $fqcn,
                $specFile,
            ));

            $this->output->fileDisplay($specFile, $this->filesystem->read($specFile), true);
        }

        // Offer class generation — gate on the file the generator would write,
        // not class_exists(), which reports false for a class whose source is
        // right there but fails to autoload (a PSR-4 mismatch) and would have us
        // offer to create a file that already exists.
        $location = ClassLocation::for($fqcn, ltrim($this->config->getSrcPath(), './'), $this->config->getPsr4Prefix());

        if (!$location->exists($this->filesystem)) {
            $question = sprintf('Do you want me to create class <fg=white>%s</> for you?', $fqcn);
            if ($this->chooser->choose($question, 'create-class', 'create classes')) {
                try {
                    $message = $this->classGenerator->generate($fqcn);
                    $this->output->success($message);

                    if ($this->filesystem->exists($location->filePath())) {
                        $this->output->fileDisplay($location->filePath(), $this->filesystem->read($location->filePath()), true);
                    }
                } catch (RuntimeException $e) {
                    $this->output->error($e->getMessage());
                }
            }
        }

        // Offer to run specs
        if ($this->chooser->choose('Do you want to run specs now?', 'run-specs', 'run specs')) {
            $this->handleRun('');
        }

        return self::CONTINUE;
    }

    /**
     * Adds an it() example for a method to a spec file, generating the spec if needed.
     */
    private function handleExemplify(string $argument): int
    {
        $parts = explode(' ', $argument, 2);
        $fqcn = $parts[0];
        $method = $parts[1] ?? '';

        if ($fqcn === '' || $method === '') {
            $this->output->error('Usage: /exemplify Acme\Calculator add');
            return self::CONTINUE;
        }

        $spec = str_replace('\\', '/', $fqcn);

        $specPath = $this->specGenerator->getSpecPath();
        $specFile = getcwd() . DIRECTORY_SEPARATOR
            . $specPath . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $spec)
            . $this->specGenerator->getSpecSuffix();

        // Generate spec if it doesn't exist
        $this->output->getOutput()->writeln('');
        $specExisted = $this->filesystem->exists($specFile);
        if (!$specExisted) {
            try {
                $this->specGenerator->generate($spec);
            } catch (RuntimeException $e) {
                $this->output->error($e->getMessage());
                return self::CONTINUE;
            }

            $this->output->getOutput()->writeln(sprintf(
                '  Specification for <fg=bright-blue>%s</> created in <fg=cyan>%s</>',
                $fqcn,
                $specFile,
            ));
        }

        $before = $specExisted ? $this->filesystem->read($specFile) : '';

        // Add the example (no-op if this method is already exemplified)
        $added = $this->specGenerator->addExample($spec, $method);

        if (!$added && $specExisted) {
            $this->output->getOutput()->writeln(sprintf(
                '  <fg=gray>An example for %s::%s already exists.</>',
                $fqcn,
                $method,
            ));
        } else {
            $this->output->getOutput()->writeln(sprintf(
                '  Example for <fg=bright-blue>%s::%s</> added.',
                $fqcn,
                $method,
            ));

            // Show a full listing for a brand-new spec, a diff for an existing one
            // (so only the added example is marked as new, not the whole file).
            if ($this->filesystem->exists($specFile)) {
                $after = $this->filesystem->read($specFile);
                if ($specExisted) {
                    $this->output->fileDiff($specFile, $before, $after);
                } else {
                    $this->output->fileDisplay($specFile, $after, true);
                }
            }
        }

        // Offer to run specs
        if ($this->chooser->choose('Do you want to run specs now?', 'run-specs', 'run specs')) {
            $this->handleRun('');
        }

        return self::CONTINUE;
    }

    /**
     * Turns leading suite keywords into their run options ("features" into
     * --story, "all" into --all, "specs" into the default), leaving everything
     * from the first non-keyword token on untouched, so an option value that
     * happens to match a keyword (--filter features) is never rewritten.
     */
    private function runArgument(string $tail): string
    {
        $tail = trim($tail);
        if ($tail === '') {
            return '';
        }

        $tokens = preg_split('/\s+/', $tail) ?: [];
        $translated = [];
        foreach ($tokens as $i => $token) {
            $mapped = match (strtolower($token)) {
                'features', 'feature', 'stories', 'story' => '--story',
                'all', 'everything' => '--all',
                'specs', 'spec' => '',
                default => null,
            };

            if ($mapped === null) {
                $translated = array_merge($translated, array_slice($tokens, $i));

                break;
            }

            if ($mapped !== '') {
                $translated[] = $mapped;
            }
        }

        return implode(' ', $translated);
    }

    /**
     * Runs specs, then offers to generate any missing code.
     *
     * The run is delegated to the SpecRunner (a fresh subprocess in production)
     * so that code generated earlier in the session is actually loaded; the
     * interactive generation then runs here, in the REPL, keeping the shared
     * numbered chooser.
     */
    private function handleRun(string $argument): int
    {
        $outcome = $this->specRunner->run($argument, $this->output->getOutput());
        $this->lastSituation = $outcome->summary ?? $this->lastSituation;

        $candidates = $outcome?->candidates;
        if ($candidates !== null && !$candidates->isEmpty()) {
            $this->offerGeneration($candidates);
        }

        return self::CONTINUE;
    }

    /**
     * Interactively offers to generate each reported candidate, using the
     * shared numbered chooser.
     *
     * @param GenerationCandidates $candidates the candidates the run reported
     */
    private function offerGeneration(GenerationCandidates $candidates): void
    {
        $codeGenerator = new CodeGenerator(
            ltrim($this->config->getSrcPath(), './'),
            ltrim($this->config->getSpecPath(), './'),
            $this->interactive,
            $this->config->getSpecSuffix(),
            $this->config->getPsr4Prefix(),
            $this->chooser,
        );
        $codeGenerator->apply($this->output->getOutput(), $candidates, false);
    }

    /**
     * Handles a command that isn't one of the built-in arms: delegates to a
     * registered Application command of the same name (minus the slash), or
     * reports it as unknown.
     */
    private function handleSlashCommand(string $command, string $tail): int
    {
        $name = ltrim($command, '/');

        if ($this->application !== null && $this->application->has($name)) {
            if ($this->application->find($name) instanceof Pair) {
                return self::CONTINUE;
            }

            return $this->delegateToCommand($name, $tail);
        }

        return $this->handleUnknown($command);
    }

    /**
     * Runs a registered command with the raw argument tail of the input,
     * through the Application itself (the canonical path), so argument
     * binding and definition merging behave exactly as on the command line.
     */
    private function delegateToCommand(string $name, string $argString): int
    {
        if ($this->application === null) {
            return self::CONTINUE;
        }

        $input = new StringInput(trim($name . ' ' . $argString));
        $input->setInteractive($this->interactive);

        try {
            $this->application->doRun($input, $this->output->getOutput());
        } catch (Exception $e) {
            $this->output->error($e->getMessage());
        }

        return self::CONTINUE;
    }

    /**
     * Turns a natural-language instruction into one AI-authored file edit, shows
     * it as a diff, and writes it once confirmed. Requires an AI provider. A
     * note typed at the chooser (Tab, or "3, ..." piped) is a course correction:
     * it becomes the follow-up instruction for another round through the same
     * pipeline, bounded so a session cannot loop forever.
     */
    private function handleGenerate(string $argument): int
    {
        $instruction = trim($argument);
        if ($instruction === '') {
            $this->output->error('Usage: /generate <what to build in plain English>');

            return self::CONTINUE;
        }

        $aiConfig = $this->config->getAiConfig();
        if ($aiConfig === null) {
            $this->output->error('AI configuration required for /generate: add an "ai" section to phpspec.yaml.');

            return self::CONTINUE;
        }

        $writer = new Writer($this->filesystem);
        $original = $instruction;

        for ($round = 0; $round < 3; $round++) {
            $outcome = $this->agent->chat('generate', $instruction);
            if ($outcome->proposals === []) {
                $reason = $outcome->prose !== '' ? $outcome->prose : 'Could not generate anything for that instruction. Try rephrasing.';
                if ($this->ai !== null) {
                    // /generate is the context-free one-shot; the assistant holds
                    // the conversation, so steer a miss back to plain English.
                    $reason .= ' Or just tell me in plain English; I hold the pair context that /generate does not.';
                }
                $this->output->error($reason);

                return self::CONTINUE;
            }

            $note = '';
            $notedPath = '';
            $noteFollowsApply = false;
            foreach ($outcome->proposals as $proposal) {
                if ($proposal->isNew) {
                    $this->output->fileDisplay($proposal->path, $proposal->new, true);
                } else {
                    $this->output->fileDiff($proposal->path, $proposal->old, $proposal->new);
                }

                $applied = $this->chooser->choose('Apply this change?', 'generate', 'apply generated changes');
                if ($applied) {
                    $writer->apply($proposal);
                    $this->output->success(($proposal->isNew ? 'Created ' : 'Updated ') . $proposal->path);
                }

                if ($note === '' && $this->chooser->lastNote() !== '') {
                    $note = $this->chooser->lastNote();
                    $notedPath = $proposal->path;
                    $noteFollowsApply = $applied;
                }
            }

            if ($note === '') {
                return self::CONTINUE;
            }

            $this->output->getOutput()->writeln(sprintf('  <fg=gray>Following up: %s</>', $note));
            $instruction = $original . ' ' . sprintf(
                $noteFollowsApply
                    ? 'The proposal for %s was applied. The human then asked: "%s".'
                    : 'The human declined the proposal for %s, saying: "%s". Address that.',
                $notedPath,
                $note,
            );
        }

        return self::CONTINUE;
    }

    /**
     * Clears the terminal screen.
     */
    private function handleClear(): int
    {
        $this->output->clearScreen();
        return self::CONTINUE;
    }

    /**
     * Displays available commands.
     */
    private function handleHelp(): int
    {
        $out = $this->output->getOutput();
        $out->writeln('');
        $out->writeln('  <fg=bright-blue;options=bold>Available commands:</>');
        $out->writeln('');
        $out->writeln('  <fg=white>/describe</> <fg=gray>Acme\Greeter</>          Generate a spec file');
        $out->writeln('  <fg=white>/exemplify</> <fg=gray>Acme\Greeter greet</> Add an example for a method');
        $out->writeln('  <fg=white>/run</>                               Run all specs');
        $out->writeln('  <fg=white>/run</> <fg=gray>spec/path</>                   Run specs at path');
        $out->writeln('  <fg=white>/next</>                              Suggest the next step');
        $out->writeln('  <fg=white>/generate</> <fg=gray>what to build</>        Generate a spec or code from words (AI)');
        $out->writeln('  <fg=white>/clear</>                    Clear the screen');
        $out->writeln("  <fg=white>/swap</>                     Swap who drives (you \u{21c4} AI)");
        $out->writeln('  <fg=white>/help</>                     Show this help');
        $out->writeln('  <fg=white>/quit</>                     Exit pair mode');

        $this->listApplicationCommands($out);

        $out->writeln('');

        if ($this->ai !== null) {
            $out->writeln('  <fg=bright-blue;options=bold>AI assistant</> <fg=green>(available)</>');
            $out->writeln('  <fg=gray>Right now — ' . $this->roleState->current()->contractLine() . '</>');
        } elseif ($this->config->get('ai') !== null) {
            $out->writeln('  <fg=bright-blue;options=bold>AI assistant</> <fg=yellow>(configured, but unavailable)</>');
            if ($this->aiUnavailableReason !== null) {
                $out->writeln('  <fg=gray>' . $this->aiUnavailableReason . '</>');
            }
        } else {
            $out->writeln('  <fg=bright-blue;options=bold>AI assistant</> <fg=gray>(not configured — add ai: section to phpspec.yml)</>');
        }
        $out->writeln('');
        $out->writeln('  Commands also work as plain words: <fg=gray>run features</>, <fg=gray>run --all</>, <fg=gray>describe App\Basket</>.');
        $out->writeln('  Anything else without a leading <fg=white>/</> is sent to the AI assistant.');
        $out->writeln('  It can generate specs, features, step definitions, and run your specs.');
        $out->writeln('');
        $out->writeln('  <fg=bright-blue>Examples:</>');
        $out->writeln('  <fg=gray>> write a spec for a Calculator that adds and subtracts</>');
        $out->writeln('  <fg=gray>> create a feature scenario for user registration</>');
        $out->writeln('  <fg=gray>> add step definitions for the login feature</>');
        $out->writeln('  <fg=gray>> run my specs and tell me what\'s failing</>');
        $out->writeln('  <fg=gray>> explain how the Loader class works</>');
        $out->writeln('');

        return self::CONTINUE;
    }

    /**
     * Lists additional commands from the Application that aren't already handled by the REPL.
     */
    private function listApplicationCommands(\Symfony\Component\Console\Output\OutputInterface $out): void
    {
        if ($this->application === null) {
            return;
        }

        $skip = ['pair', 'describe', 'exemplify', 'run', 'next', 'list', 'help', 'completion', '_complete'];
        $extra = [];

        foreach ($this->application->all() as $name => $cmd) {
            if (in_array($name, $skip, true) || str_contains($name, ':')) {
                continue;
            }
            $extra[$name] = $cmd->getDescription();
        }

        if ($extra === []) {
            return;
        }

        ksort($extra);
        $out->writeln('');
        $out->writeln('  <fg=bright-blue;options=bold>Additional commands:</>');
        $out->writeln('');
        foreach ($extra as $name => $description) {
            $out->writeln(sprintf('  <fg=white>%s</>  %s', str_pad('/' . $name, 22), $description));
        }
    }

    /**
     * Routes input to the AI assistant, falling back to unknown-command error.
     */
    private function handleAi(string $input): int
    {
        if ($this->ai === null) {
            return $this->handleAiUnavailable();
        }

        $this->ai->handle($input, $this->lastSituation);

        return self::CONTINUE;
    }

    /**
     * Explains why a natural-language line could not reach the AI: either a
     * configured provider failed to start (with the captured reason), or no
     * provider is configured at all.
     */
    private function handleAiUnavailable(): int
    {
        if ($this->aiUnavailableReason !== null) {
            $this->output->error(
                "AI is configured but its provider could not start, so I can't use natural language:\n"
                . '  ' . $this->aiUnavailableReason,
            );

            return self::CONTINUE;
        }

        $this->output->error(
            "No AI provider is configured, so I can't use natural language.\n"
            . '  Add an "ai" section to phpspec.yaml, or type /help for the available commands.',
        );

        return self::CONTINUE;
    }

    /**
     * Displays an error for an unrecognized slash command.
     */
    private function handleUnknown(string $command): int
    {
        $this->output->error("Unknown command: $command. Type /help for the available commands.");

        return self::CONTINUE;
    }

    /**
     * Registers a fallback autoloader for classes generated during the session.
     *
     * Composer caches "not found" results in $missingClasses, so newly
     * created files won't be found on subsequent autoload attempts.
     * This PSR-0 autoloader checks the filesystem on every call.
     */
    private function registerAutoloader(): void
    {
        $srcPath = rtrim($this->config->getSrcPath(), '/.');
        if ($srcPath === '') {
            $srcPath = '.';
        }

        spl_autoload_register(function (string $class) use ($srcPath) {
            $file = $srcPath . '/' . str_replace('\\', '/', $class) . '.php';
            if (file_exists($file)) {
                require $file;
            }
        });

        $this->config->registerAutoloaders();
    }
}
