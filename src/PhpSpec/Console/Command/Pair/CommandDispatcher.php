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

use PhpSpec\Ai\ProviderFactory;
use PhpSpec\CodeGeneration\ClassGenerator;
use PhpSpec\CodeGeneration\ClassLocation;
use PhpSpec\CodeGeneration\SpecGenerator;
use PhpSpec\Configuration;
use PhpSpec\Console\Command\Generate\GenerateAgent;
use PhpSpec\Console\Command\Pair;
use PhpSpec\Console\Command\Run\CodeGenerator;
use PhpSpec\Console\Command\Run\GenerationCandidates;
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
    private readonly GenerateAgent $generateAgent;
    private ?AiAssistant $ai = null;

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
        ?GenerateAgent $generateAgent = null,
    ) {
        $this->parser = new InputParser();
        $this->filesystem = $filesystem ?? new RealFilesystem();
        $this->chooser = $chooser ?? new Chooser($output, $interactive);
        $this->specRunner = $specRunner ?? new SubprocessRunner();
        $this->roleState = $roleState ?? new RoleState();
        $this->generateAgent = $generateAgent ?? new GenerateAgent($this->config, $this->filesystem);
        $this->registerAutoloader();

        $aiConfig = $this->config->getAiConfig();
        if ($aiConfig !== null) {
            try {
                $provider = ProviderFactory::create($aiConfig);
                $model = $aiConfig['model'] ?? ProviderFactory::defaultModel($aiConfig['provider']);
                $this->ai = new AiAssistant($provider, $this->config, $this->output, $model, $this->filesystem, $this->interactive, $this->extensionLoader, $this->chooser, $this->roleState);
            } catch (RuntimeException $e) {
                $this->output->error($e->getMessage());
            }
        }
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
     * Suggests the single next step from a real suite run rather than a guess,
     * so it never loops describing a spec that already exists: red means run and
     * fix, a pending example is the nearest gap, an empty project starts a spec.
     * While the human navigates it coaches; while the AI drives it takes the
     * step itself (one artifact).
     */
    private function handleNext(): int
    {
        $outcome = $this->specRunner->run('', new BufferedOutput());
        $this->lastSituation = $outcome->summary ?? $this->lastSituation;
        $role = $this->roleState->current();
        $next = (new SuiteNarrator())->next($outcome, $role);

        $console = $this->output->getOutput();
        foreach ($next['lines'] as $line) {
            $console->writeln($line);
        }

        if ($role->aiIsDriver() && $this->ai !== null && $next['action'] !== 'observe') {
            $this->ai->handle('Take the single most valuable next step now, as one artifact, then hand back.', $this->lastSituation);
        }

        return self::CONTINUE;
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

        // A leading slash is the one signal that this line is a command; every
        // other line is a prompt for the AI (or the unknown-command hint when no
        // AI is configured). No keyword guessing, no argument-count heuristics.
        if (!str_starts_with($command, '/')) {
            $this->suggestion = null;

            return $this->handleAi($input);
        }

        $result = match ($command) {
            '/describe' => $this->handleDescribe($parsed['argument']),
            '/exemplify' => $this->handleExemplify($parsed['argument']),
            '/run' => $this->handleRun($parsed['argument']),
            '/next' => $this->handleNext(),
            '/generate' => $this->handleGenerate($parsed['argument']),
            '/clear' => $this->handleClear(),
            '/swap' => $this->handleSwap(),
            '/help' => $this->handleHelp(),
            '/quit', '/exit' => self::QUIT,
            default => $this->handleSlashCommand($command, $input),
        };

        // Set from the top-level command only, so a nested run (e.g. the "run
        // now?" step of /describe) never clobbers the hint.
        $this->suggestion = $this->suggestionFor($command, $parsed['argument']);

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
     * Handles a slash command that isn't one of the built-in arms: delegates to
     * a registered Application command of the same name (minus the slash), or
     * reports it as unknown.
     */
    private function handleSlashCommand(string $command, string $rawInput): int
    {
        $name = ltrim($command, '/');

        if ($this->application !== null && $this->application->has($name)) {
            $cmd = $this->application->find($name);
            if ($cmd instanceof Pair) {
                return self::CONTINUE;
            }

            return $this->delegateToCommand($cmd, $command, $rawInput);
        }

        return $this->handleUnknown($command);
    }

    /**
     * Runs a Symfony console command with arguments parsed from raw input.
     */
    private function delegateToCommand(Command $cmd, string $commandToken, string $rawInput): int
    {
        $argString = trim(substr($rawInput, strlen($commandToken)));
        $input = new StringInput($argString);
        $input->setInteractive($this->interactive);

        try {
            $input->bind($cmd->getDefinition());
            $cmd->run($input, $this->output->getOutput());
        } catch (\Exception $e) {
            $this->output->error($e->getMessage());
        }

        return self::CONTINUE;
    }

    /**
     * Turns a natural-language instruction into one AI-authored file edit, shows
     * it as a diff, and writes it once confirmed. Requires an AI provider.
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
            $this->output->error('AI configuration required for /generate — add an "ai" section to phpspec.yaml.');

            return self::CONTINUE;
        }

        $proposal = $this->generateAgent->propose($aiConfig, $instruction);
        if ($proposal === null) {
            $this->output->error('Could not generate anything for that instruction. Try rephrasing.');

            return self::CONTINUE;
        }

        if ($proposal['isNew']) {
            $this->output->fileDisplay($proposal['path'], $proposal['new'], true);
        } else {
            $this->output->fileDiff($proposal['path'], $proposal['old'], $proposal['new']);
        }

        if ($this->chooser->choose('Apply this change?', 'generate', 'apply generated changes')) {
            $this->generateAgent->write($proposal);
            $this->output->success(($proposal['isNew'] ? 'Created ' : 'Updated ') . $proposal['path']);
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

        $aiAvailable = $this->config->get('ai') !== null;
        if ($aiAvailable) {
            $out->writeln('  <fg=bright-blue;options=bold>AI assistant</> <fg=green>(available)</>');
            $out->writeln('  <fg=gray>Right now — ' . $this->roleState->current()->contractLine() . '</>');
        } else {
            $out->writeln('  <fg=bright-blue;options=bold>AI assistant</> <fg=gray>(not configured — add ai: section to phpspec.yml)</>');
        }
        $out->writeln('');
        $out->writeln('  Anything without a leading <fg=white>/</> is sent to the AI assistant.');
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
            return $this->handleUnknown($this->parser->parse($input)['command']);
        }

        $this->ai->handle($input, $this->lastSituation);
        return self::CONTINUE;
    }

    /**
     * Displays an error for an unrecognized command.
     */
    private function handleUnknown(string $command): int
    {
        $this->output->error(
            "Unknown command: $command. Type /help for available commands.\n"
            . '  To use natural language, configure an AI provider in phpspec.yaml.',
        );
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
