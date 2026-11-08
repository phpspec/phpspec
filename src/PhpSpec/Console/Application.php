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

namespace PhpSpec\Console;

use Composer\InstalledVersions;

use function PhpSpec\attach;

use PhpSpec\Browser\Browser;
use PhpSpec\Browser\BrowserRegistry;
use PhpSpec\Browser\Client;
use PhpSpec\BrowserAdapter;
use PhpSpec\CodeGeneration\ClassGenerator;
use PhpSpec\CodeGeneration\SpecGenerator;
use PhpSpec\Configuration;
use PhpSpec\ConfigurationException;
use PhpSpec\Console\Command\Accept;
use PhpSpec\Console\Command\Api;
use PhpSpec\Console\Command\Describe;
use PhpSpec\Console\Command\Exemplify;
use PhpSpec\Console\Command\Generate;
use PhpSpec\Console\Command\Guard;
use PhpSpec\Console\Command\Next;
use PhpSpec\Console\Command\Pair;
use PhpSpec\Console\Command\Refactor;
use PhpSpec\Console\Command\Run;
use PhpSpec\Coverage\CoverageEngines;
use PhpSpec\Extensions\ExtensionLoader;
use PhpSpec\Loader;
use PhpSpec\Report\Formatter\Agent;
use PhpSpec\Report\Formatter\Agent\Schema;
use PhpSpec\Runner;
use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\ExceptionInterface as ConsoleException;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\StreamableInputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 * Symfony Console application that bootstraps PhpSpec by registering the Run and Describe commands
 * with their required dependencies.
 */
final class Application extends BaseApplication
{
    /** @var array<int, string> raw argv tokens, used to resolve --config before commands are built */
    private readonly array $argv;

    /**
     * @param array<int, string>|null $argv raw argv tokens; defaults to the process argv (in-process runners pass their own)
     */
    public function __construct(?array $argv = null)
    {
        $globalArgv = $_SERVER['argv'] ?? null;
        $this->argv = $argv ?? (is_array($globalArgv) ? $globalArgv : []);

        parent::__construct('phpspec', $this->installedVersion());
    }

    /**
     * Adds the application-level --config option so every command accepts it.
     *
     * @return InputDefinition the default input definition
     */
    public function getDefaultInputDefinition(): InputDefinition
    {
        $definition = parent::getDefaultInputDefinition();
        $definition->addOption(new InputOption(
            'config',
            'c',
            InputOption::VALUE_REQUIRED,
            'Path to a phpspec configuration file (overrides the working directory lookup)',
        ));

        return $definition;
    }

    /**
     * A configuration that cannot be taken as written is said in one line,
     * naming the file, where the console would otherwise box the exception
     * with the source file it was thrown from. A command line the console
     * cannot read (an option the command does not take, a value missing, a
     * command that does not exist) is the console's to explain to a person,
     * and answers a coding agent on its own channel like any other refusal.
     */
    public function doRun(InputInterface $input, OutputInterface $output): int
    {
        try {
            return parent::doRun($input, $output);
        } catch (ConfigurationException $e) {
            return $this->refuse($input, $output, $e->getMessage());
        } catch (ConsoleException $e) {
            if (!$this->answersAnAgent($input)) {
                throw $e;
            }

            return $this->refuse($input, $output, $e->getMessage());
        }
    }

    /**
     * Asks nobody when no terminal is there to answer: an input that is a pipe
     * or a file may stay open and never say anything. SHELL_INTERACTIVE=1
     * lets answers piped on purpose through, and --no-interaction wins over it.
     */
    protected function configureIO(InputInterface $input, OutputInterface $output): void
    {
        parent::configureIO($input, $output);

        if (getenv('SHELL_INTERACTIVE') !== '1' && !stream_isatty($this->answersFrom($input))) {
            $input->setInteractive(false);
        }
    }

    /**
     * Says what stops the command before it starts, on the channel the
     * caller reads: a coding agent gets a run's fatal and summary, or a
     * command's error receipt, rather than prose in its JSON.
     */
    private function refuse(InputInterface $input, OutputInterface $output, string $problem): int
    {
        if (!$this->answersAnAgent($input)) {
            $output->writeln(sprintf('<fg=red>%s</>', $problem));

            return 1;
        }

        $command = $input->getFirstArgument() ?? 'run';

        if ($command === 'run') {
            $document = new Agent($output);
            $document->stopped($problem);
            $document->publish();

            return 1;
        }

        $output->write((json_encode(['v' => Schema::V, 'action' => $command, 'error' => $problem], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}') . "\n", false, OutputInterface::OUTPUT_RAW);

        return 1;
    }

    /**
     * Whether the caller reads the agent format, as the raw command line asks
     * for it: the input may be past reading against any definition.
     */
    private function answersAnAgent(InputInterface $input): bool
    {
        return $input->getParameterOption(['--format', '-f'], 'pretty', true) === 'agent';
    }

    /**
     * Returns the default commands including the PhpSpec run and describe commands.
     *
     * @return array<Command> the list of default console commands
     */
    public function getDefaultCommands(): array
    {
        $config = Configuration::load('.', configFile: Configuration::configPathFromArgv($this->argv));

        $config->registerAutoloaders();
        $extensionLoader = new ExtensionLoader($config);
        $extensionLoader->load();
        $this->wireBrowser($config, $extensionLoader->getBrowser());

        $defaultCommands = array_values(array_filter(
            parent::getDefaultCommands(),
            fn(Command $cmd) => !in_array($cmd->getName(), ['completion', '_complete', 'help'], true),
        ));
        $defaultCommands[] = new Help();
        $specSuffix = $config->getSpecSuffix();
        $defaultCommands[] = new Run(
            new Loader(specSuffix: $specSuffix, featuresPath: $config->getFeaturesPath(), stepsPath: $config->getStepsPath()),
            new Runner(),
            $config,
            $extensionLoader,
            engines: CoverageEngines::of($config, $this->argv),
        );
        $defaultCommands[] = new Describe(
            new SpecGenerator($config->specDir(), specSuffix: $specSuffix),
            $config->getSourceLayout(),
            composer: $config->composerJson(),
        );
        $defaultCommands[] = new Exemplify(
            new SpecGenerator($config->specDir(), specSuffix: $specSuffix),
            $config->getSourceLayout(),
        );
        $defaultCommands[] = new Pair(
            new SpecGenerator($config->specDir(), specSuffix: $specSuffix),
            new ClassGenerator($config->getSourceLayout()),
            $config,
            $extensionLoader,
        );
        $defaultCommands[] = new Refactor($config);
        $defaultCommands[] = new Next($config);
        $defaultCommands[] = new Generate($config);
        $defaultCommands[] = new Accept(config: $config);
        $defaultCommands[] = new Guard(config: $config);
        $defaultCommands[] = new Api();

        foreach ($extensionLoader->getCommands() as $cmd) {
            $defaultCommands[] = $cmd;
        }

        return $defaultCommands;
    }

    /**
     * Puts the default browser behind the DSL, reporting every exchange as
     * attachments so a failed response assertion is read next to the body
     * that explains it.
     */
    private function wireBrowser(Configuration $config, ?Browser $browser): void
    {
        BrowserRegistry::use(new BrowserAdapter($browser ?? new Client(), static function (string $method, string $url, int $status, string $body): void {
            attach('http.request', strtoupper($method) . ' ' . $url . ' (' . $status . ')');
            attach('http.response', $body);
        }));

        $baseUrl = $config->getBaseUrl();
        if ($baseUrl !== null) {
            BrowserRegistry::init($baseUrl);
        }
    }

    /**
     * Where a question's answer is read: the input's own stream when it has
     * one, else the standard input.
     *
     * @return resource
     */
    private function answersFrom(InputInterface $input)
    {
        if ($input instanceof StreamableInputInterface && $input->getStream() !== null) {
            return $input->getStream();
        }

        return STDIN;
    }

    private function installedVersion(): string
    {
        if (!InstalledVersions::isInstalled('phpspec/phpspec')) {
            return 'UNKNOWN';
        }

        return InstalledVersions::getPrettyVersion('phpspec/phpspec') ?? 'UNKNOWN';
    }
}
