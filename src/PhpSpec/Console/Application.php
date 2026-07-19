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

use PhpSpec\CodeGeneration\ClassGenerator;
use PhpSpec\CodeGeneration\SpecGenerator;
use PhpSpec\Configuration;
use PhpSpec\Console\Command\Describe;
use PhpSpec\Console\Command\Exemplify;
use PhpSpec\Console\Command\Generate;
use PhpSpec\Console\Command\Next;
use PhpSpec\Console\Command\Pair;
use PhpSpec\Console\Command\Refactor;
use PhpSpec\Console\Command\Run;
use PhpSpec\Extensions\ExtensionLoader;
use PhpSpec\Loader;
use PhpSpec\Runner;
use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;

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
     * @param string $version the application version
     * @param array<int, string>|null $argv raw argv tokens; defaults to the process argv (in-process runners pass their own)
     */
    public function __construct(string $version = 'UNKNOWN', ?array $argv = null)
    {
        $globalArgv = $_SERVER['argv'] ?? null;
        $this->argv = $argv ?? (is_array($globalArgv) ? $globalArgv : []);

        parent::__construct($version);
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
     * Returns the default commands including the PhpSpec run and describe commands.
     *
     * @return array<Command> the list of default console commands
     */
    public function getDefaultCommands(): array
    {
        $config = new Configuration('.', configFile: Configuration::configPathFromArgv($this->argv));

        $config->registerAutoloaders();
        $extensionLoader = new ExtensionLoader($config);
        $extensionLoader->load();

        $defaultCommands = array_values(array_filter(
            parent::getDefaultCommands(),
            fn(Command $cmd) => !in_array($cmd->getName(), ['completion', '_complete'], true),
        ));
        $specSuffix = $config->getSpecSuffix();
        $defaultCommands[] = new Run(
            new Loader(specSuffix: $specSuffix, featuresPath: $config->getFeaturesPath(), stepsPath: $config->getStepsPath()),
            new Runner(),
            $config,
            $extensionLoader,
        );
        $defaultCommands[] = new Describe(
            new SpecGenerator(ltrim($config->getSpecPath(), './'), specSuffix: $specSuffix),
        );
        $defaultCommands[] = new Exemplify(
            new SpecGenerator(ltrim($config->getSpecPath(), './'), specSuffix: $specSuffix),
        );
        $defaultCommands[] = new Pair(
            new SpecGenerator(ltrim($config->getSpecPath(), './'), specSuffix: $specSuffix),
            new ClassGenerator(ltrim($config->getSrcPath(), './'), psr4Prefix: $config->getPsr4Prefix()),
            $config,
            $extensionLoader,
        );
        $defaultCommands[] = new Refactor($config);
        $defaultCommands[] = new Next($config);
        $defaultCommands[] = new Generate($config);

        foreach ($extensionLoader->getCommands() as $cmd) {
            $defaultCommands[] = $cmd;
        }

        return $defaultCommands;
    }
}
