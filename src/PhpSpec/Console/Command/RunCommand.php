<?php

/*
 * This file is part of PhpSpec, A php toolset to drive emergent
 * design by specification.
 *
 * (c) Marcello Duarte <marcello.duarte@gmail.com>
 * (c) Konstantin Kudryashov <ever.zet@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpSpec\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Main command, responsible to run the specs
 */
class RunCommand extends Command
{
    /**
     *
     */
    public function __construct()
    {
        parent::__construct('run');

        $this->setDefinition(array(
            new InputArgument('spec', InputArgument::OPTIONAL, 'Specs to run'),
            new InputOption('format', 'f', InputOption::VALUE_REQUIRED, 'Formatter'),
            new InputOption('stop-on-failure', null , InputOption::VALUE_NONE, 'Stop on failure'),
            new InputOption('no-code-generation', null , InputOption::VALUE_NONE, 'Do not prompt for missing method/class generation'),
        ));
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return mixed
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $container = $this->getApplication()->getContainer();
        $container->setParam('formatter.name',
            $input->getOption('format') ?: $container->getParam('formatter.name')
        );
        $container->configure();

        $locator = $input->getArgument('spec');
        $linenum = null;
        if (preg_match('/^(.*)\:(\d+)$/', $locator, $matches)) {
            list($_, $locator, $linenum) = $matches;
        }

        $suite       = $container->get('loader.resource_loader')->load($locator, $linenum);
        $suiteRunner = $container->get('runner.suite');

        return $suiteRunner->run($suite);
    }
}
