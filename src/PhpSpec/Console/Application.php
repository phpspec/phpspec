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
use PhpSpec\Console\Command\Next;
use PhpSpec\Console\Command\Pair;
use PhpSpec\Console\Command\Refactor;
use PhpSpec\Console\Command\Run;
use PhpSpec\EventDispatcher\Dispatcher;
use PhpSpec\EventDispatcher\DispatcherRegistry;
use PhpSpec\Extensions\ExtensionLoader;
use PhpSpec\Loader;
use PhpSpec\Runner;
use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Command\Command;

/**
 * Symfony Console application that bootstraps PhpSpec by registering the Run and Describe commands
 * with their required dependencies.
 */
final class Application extends BaseApplication
{
    /**
     * Returns the default commands including the PhpSpec run and describe commands.
     *
     * @return array<Command> the list of default console commands
     */
    public function getDefaultCommands(): array
    {
        $config = new Configuration('.');

        $dispatcher = new Dispatcher();
        DispatcherRegistry::set($dispatcher);

        $config->registerAutoloaders();
        $extensionLoader = new ExtensionLoader($config, $dispatcher);
        $extensionLoader->load();

        $defaultCommands = array_values(array_filter(
            parent::getDefaultCommands(),
            fn(Command $cmd) => !in_array($cmd->getName(), ['completion', '_complete'], true),
        ));
        $specSuffix = $config->getSpecSuffix();
        $defaultCommands[] = new Run(
            new Loader($dispatcher, specSuffix: $specSuffix),
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
            new Loader($dispatcher, specSuffix: $specSuffix),
            new Runner(),
            new SpecGenerator(ltrim($config->getSpecPath(), './'), specSuffix: $specSuffix),
            new ClassGenerator(ltrim($config->getSrcPath(), './')),
            $config,
            $extensionLoader,
        );
        $defaultCommands[] = new Refactor($config);
        $defaultCommands[] = new Next($config);

        foreach ($extensionLoader->getCommands() as $cmd) {
            $defaultCommands[] = $cmd;
        }

        return $defaultCommands;
    }
}
