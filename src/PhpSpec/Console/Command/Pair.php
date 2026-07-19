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

namespace PhpSpec\Console\Command;

use PhpSpec\CodeGeneration\ClassGenerator;
use PhpSpec\CodeGeneration\SpecGenerator;
use PhpSpec\Configuration;
use PhpSpec\Console\Command\Pair\CommandDispatcher;
use PhpSpec\Console\Command\Pair\PairOutput;
use PhpSpec\Console\Command\Pair\Repl;
use PhpSpec\Console\Command\Pair\RoleState;
use PhpSpec\Console\Command\Pair\StatusBar;
use PhpSpec\Extensions\ExtensionLoader;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface as Input;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface as Output;

/**
 * @internal
 * CLI command that launches an interactive pair programming REPL session.
 */
final class Pair extends Command
{
    /**
     * @param SpecGenerator $specGenerator the spec file generator
     * @param ClassGenerator $classGenerator the class file generator
     * @param Configuration $config the project configuration
     * @param ExtensionLoader|null $extensionLoader optional extension loader for custom commands and tools
     */
    public function __construct(
        private readonly SpecGenerator $specGenerator,
        private readonly ClassGenerator $classGenerator,
        private readonly Configuration $config,
        private readonly ?ExtensionLoader $extensionLoader = null,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('pair')
            ->setDescription('Interactive pair programming mode')
            ->addOption('prompt', null, InputOption::VALUE_REQUIRED, 'Run a single prompt without entering interactive mode');
    }

    protected function execute(Input $input, Output $output): int
    {
        $prompt = $input->getOption('prompt');

        if ($prompt === null && !$input->isInteractive()) {
            $output->writeln('<fg=red>Pair mode requires an interactive terminal.</>');
            return 1;
        }

        $bootstrap = $this->config->getBootstrap() ?? (file_exists('vendor/autoload.php') ? 'vendor/autoload.php' : null);
        if ($bootstrap !== null && file_exists($bootstrap)) {
            require $bootstrap;
        }

        $roleState = new RoleState();
        $pairOutput = new PairOutput($output);

        $dispatcher = new CommandDispatcher(
            $this->specGenerator,
            $this->classGenerator,
            $this->config,
            $pairOutput,
            $prompt === null, // non-interactive when using --prompt
            application: $this->getApplication(),
            extensionLoader: $this->extensionLoader,
            roleState: $roleState,
        );

        // The banner reflects whether the provider actually started, not merely
        // that an ai: block exists, so "ai: on" never contradicts a session that
        // can't answer natural language.
        $aiConfig = $this->config->getAiConfig();
        $pairOutput->configureStatus(
            StatusBar::abbreviateHome(getcwd() ?: '.', getenv('HOME') ?: ''),
            $dispatcher->aiIsReady(),
            is_array($aiConfig) ? $aiConfig['provider'] : null,
            $roleState,
            $dispatcher->aiUnavailableReason(),
        );

        if ($prompt !== null) {
            $dispatcher->dispatch($prompt);

            return 0;
        }

        $repl = new Repl($dispatcher, $pairOutput, $dispatcher->aiIsReady());

        return $repl->run();
    }
}
