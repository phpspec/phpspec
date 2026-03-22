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
use PhpSpec\CodeGeneration\SpecGenerator;
use PhpSpec\Configuration;
use PhpSpec\Console\Command\Pair;
use PhpSpec\Console\Command\Run\CodeGenerator;
use PhpSpec\EventDispatcher\Dispatcher;
use PhpSpec\Extensions\ExtensionLoader;
use PhpSpec\Filesystem;
use PhpSpec\Loader;
use PhpSpec\RealFilesystem;
use PhpSpec\Report\Formatter\Dot;
use PhpSpec\Result\SuiteResult;
use PhpSpec\Runner;
use RuntimeException;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\StringInput;

/**
 * Maps parsed REPL input to service calls for the pair programming mode.
 */
final class CommandDispatcher
{
    public const CONTINUE = 0;
    public const QUIT = 1;

    private InputParser $parser;
    private Filesystem $filesystem;
    private ?AiAssistant $ai = null;

    /**
     * @param Loader $loader the spec/feature file loader
     * @param Runner $runner the spec runner
     * @param SpecGenerator $specGenerator the spec file generator
     * @param ClassGenerator $classGenerator the class file generator
     * @param Configuration $config the project configuration
     * @param PairOutput $output the pair-mode output helper
     * @param bool $interactive whether to prompt for user input (false = auto-accept all)
     * @param Filesystem|null $filesystem filesystem abstraction for testability
     * @param Application|null $application the Symfony console application for command delegation
     */
    public function __construct(
        private readonly Loader $loader,
        private readonly Runner $runner,
        private readonly SpecGenerator $specGenerator,
        private readonly ClassGenerator $classGenerator,
        private readonly Configuration $config,
        private readonly PairOutput $output,
        private readonly bool $interactive = true,
        ?Filesystem $filesystem = null,
        private readonly ?Application $application = null,
        private readonly ?ExtensionLoader $extensionLoader = null,
    ) {
        $this->parser = new InputParser();
        $this->filesystem = $filesystem ?? new RealFilesystem();
        $this->registerAutoloader();

        $aiConfig = $this->config->getAiConfig();
        if ($aiConfig !== null) {
            try {
                $provider = ProviderFactory::create($aiConfig);
                $model = $aiConfig['model'] ?? ProviderFactory::defaultModel($aiConfig['provider'] ?? 'google');
                $this->ai = new AiAssistant($provider, $this->config, $this->output, $model, $this->filesystem, $this->interactive, $this->extensionLoader);
            } catch (RuntimeException $e) {
                $this->output->error($e->getMessage());
            }
        }
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

        if ($this->shouldRouteToAi($parsed['command'], $parsed['argument'])) {
            return $this->handleAi($input);
        }

        return match ($parsed['command']) {
            'describe' => $this->handleDescribe($parsed['argument']),
            'exemplify' => $this->handleExemplify($parsed['argument']),
            'run' => $this->handleRun($parsed['argument']),
            'clear' => $this->handleClear(),
            '/help' => $this->handleHelp(),
            '/quit', '/exit' => self::QUIT,
            '' => self::CONTINUE,
            default => $this->handleDefault($parsed['command'], $input),
        };
    }

    /**
     * Checks whether input that matches a command keyword should be routed
     * to AI instead, based on argument count exceeding what the command expects.
     */
    private function shouldRouteToAi(string $command, string $argument): bool
    {
        if ($this->ai === null || $argument === '') {
            return false;
        }

        return self::exceedsCommandArgLimit($command, $argument);
    }

    /**
     * Checks whether argument word count exceeds the expected limit for a command.
     */
    public static function exceedsCommandArgLimit(string $command, string $argument): bool
    {
        $maxArgs = match ($command) {
            'run','describe' => 1,
            'exemplify' => 2,
            default => 0,
        };

        $tokenCount = count(preg_split('/\s+/', $argument));

        return $maxArgs > 0 && $tokenCount > $maxArgs;
    }

    /**
     * Generates a spec file for the given class, then offers to create the class.
     */
    private function handleDescribe(string $argument): int
    {
        if ($argument === '') {
            $this->output->error('Usage: describe Acme\Greeter');
            return self::CONTINUE;
        }

        $fqcn = $argument;
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

            if ($this->filesystem->exists($specFile)) {
                $this->output->fileDisplay($specFile, $this->filesystem->read($specFile), true);
            }
        }

        // Offer class generation
        if (!class_exists($fqcn)) {
            $this->output->getOutput()->writeln('');
            $this->output->getOutput()->writeln(sprintf(
                '  <fg=yellow>Do you want me to create class <fg=white>%s</> for you?</> [Y/n] ',
                $fqcn,
            ));
            if ($this->confirm()) {
                try {
                    $message = $this->classGenerator->generate($fqcn);
                    $this->output->success($message);

                    $resolved = ClassGenerator::resolveFqcn($fqcn, ltrim($this->config->getSrcPath(), './'));
                    if ($this->filesystem->exists($resolved['filePath'])) {
                        $this->output->fileDisplay($resolved['filePath'], $this->filesystem->read($resolved['filePath']), true);
                    }
                } catch (RuntimeException $e) {
                    $this->output->error($e->getMessage());
                }
            }
        }

        // Offer to run specs
        $this->output->getOutput()->writeln('');
        $this->output->getOutput()->writeln('  <fg=yellow>Do you want to run specs now?</> [Y/n] ');
        if ($this->confirm()) {
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
        $fqcn = $parts[0] ?? '';
        $method = $parts[1] ?? '';

        if ($fqcn === '' || $method === '') {
            $this->output->error('Usage: exemplify Acme\Calculator add');
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
        if (!$this->filesystem->exists($specFile)) {
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

        // Add the example
        $this->specGenerator->addExample($spec, $method);

        $this->output->getOutput()->writeln(sprintf(
            '  Example for <fg=bright-blue>%s::%s</> added.',
            $fqcn,
            $method,
        ));

        // Display updated spec file
        if ($this->filesystem->exists($specFile)) {
            $this->output->fileDisplay($specFile, $this->filesystem->read($specFile), false);
        }

        // Offer to run specs
        $this->output->getOutput()->writeln('');
        $this->output->getOutput()->writeln('  <fg=yellow>Do you want to run specs now?</> [Y/n] ');
        if ($this->confirm()) {
            $this->handleRun('');
        }

        return self::CONTINUE;
    }

    /**
     * Runs specs, streaming results through the Dot formatter.
     */
    private function handleRun(string $argument): int
    {
        $path = $argument !== '' ? $argument : $this->config->getAllLoadPaths();

        $state = Dispatcher::saveState();
        Dispatcher::reset();

        try {
            $suite = $this->loader->load($path);

            $formatter = new Dot($this->output->getOutput());
            $formatter->begin();

            $start = hrtime(true);
            $accumulated = [];
            foreach ($this->runner->stream($suite) as $result) {
                $formatter->printResult($result);
                $accumulated[] = $result;
            }

            $results = new SuiteResult($accumulated);
            $results->setDuration((hrtime(true) - $start) / 1e9);
            $formatter->end($results);

            $srcPath = ltrim($this->config->getSrcPath(), './');
            $specPath = ltrim($this->config->getSpecPath(), './');
            $codeGenerator = new CodeGenerator($srcPath, $specPath);
            $codeGenerator->generate($this->output->getOutput(), $results, false);
        } finally {
            Dispatcher::restoreState($state);
        }

        return self::CONTINUE;
    }

    /**
     * Tries to delegate to a registered Application command, falling back to AI.
     */
    private function handleDefault(string $command, string $rawInput): int
    {
        if ($this->application !== null && $this->application->has($command)) {
            $cmd = $this->application->find($command);
            if ($cmd instanceof Pair) {
                return self::CONTINUE;
            }

            // If the argument exceeds what the command accepts, route to AI
            $parsed = $this->parser->parse($rawInput);
            if ($parsed['argument'] !== '' && $this->exceedsDefinitionArgLimit($cmd, $parsed['argument'])) {
                return $this->handleAi($rawInput);
            }

            return $this->delegateToCommand($cmd, $command, $rawInput);
        }

        return $this->handleAi($rawInput);
    }

    /**
     * Checks whether argument word count exceeds a command's defined argument limit.
     */
    private function exceedsDefinitionArgLimit(Command $cmd, string $argument): bool
    {
        $def = $cmd->getDefinition();
        $maxArgs = 0;
        foreach ($def->getArguments() as $arg) {
            if ($arg->isArray()) {
                return false;
            }
            $maxArgs++;
        }
        $tokenCount = count(preg_split('/\s+/', $argument));
        return $maxArgs === 0 || $tokenCount > $maxArgs;
    }

    /**
     * Runs a Symfony console command with arguments parsed from raw input.
     */
    private function delegateToCommand(Command $cmd, string $name, string $rawInput): int
    {
        $argString = trim(substr($rawInput, strlen($name)));
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
        $out->writeln('  <fg=white>describe</> <fg=gray>Acme\Greeter</>           Generate a spec file');
        $out->writeln('  <fg=white>exemplify</> <fg=gray>Acme\Greeter greet</>  Add an example for a method');
        $out->writeln('  <fg=white>run</>                                Run all specs');
        $out->writeln('  <fg=white>run</> <fg=gray>spec/path</>                    Run specs at path');
        $out->writeln('  <fg=white>clear</>                     Clear the screen');
        $out->writeln('  <fg=white>/help</>                     Show this help');
        $out->writeln('  <fg=white>/quit</>                     Exit pair mode');

        $this->listApplicationCommands($out);

        $out->writeln('');

        $aiAvailable = $this->config->get('ai') !== null;
        if ($aiAvailable) {
            $out->writeln('  <fg=bright-blue;options=bold>AI assistant</> <fg=green>(available)</>');
        } else {
            $out->writeln('  <fg=bright-blue;options=bold>AI assistant</> <fg=gray>(not configured — add ai: section to phpspec.yml)</>');
        }
        $out->writeln('');
        $out->writeln('  Anything that isn\'t a built-in command is sent to the AI assistant.');
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

        $skip = ['pair', 'describe', 'exemplify', 'run', 'list', 'help', 'completion', '_complete'];
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
            $out->writeln(sprintf('  <fg=white>%s</>  %s', str_pad($name, 22), $description));
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

        $this->ai->handle($input);
        return self::CONTINUE;
    }

    /**
     * Displays an error for an unrecognized command.
     */
    private function handleUnknown(string $command): int
    {
        $this->output->error("Unknown command: $command. Type /help for available commands.");
        return self::CONTINUE;
    }

    /**
     * Prompts for Y/n confirmation. Returns true if accepted.
     * In non-interactive mode, auto-accepts (returns true).
     */
    private function confirm(): bool
    {
        $answer = $this->ask('  > ');
        return $answer === '' || strtolower($answer) === 'y';
    }

    /**
     * Reads user input via readline (TTY) or fgets (pipe).
     * In non-interactive mode, returns empty string (auto-accept).
     */
    private function ask(string $prompt): string
    {
        if (!$this->interactive) {
            return '';
        }

        $this->output->prepareForInput();

        if (function_exists('readline') && stream_isatty(STDIN)) {
            $result = (string) readline($prompt);
        } else {
            $line = fgets(STDIN);
            $result = $line === false ? '' : rtrim($line, "\r\n");
        }

        $this->output->returnToContent();
        $this->output->echoInput($result ?: 'Y');

        return $result;
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
