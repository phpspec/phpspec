<?php

namespace PhpSpec\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use PhpSpec\Event;

class RunCommand extends Command
{
    public function __construct()
    {
        parent::__construct('run');

        $this->setDefinition(array(
            new InputArgument('spec', InputArgument::OPTIONAL, 'Specs to run'),
            new InputOption('format', 'f', InputOption::VALUE_REQUIRED, 'Formatter'),
        ));
    }

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

        $suite      = $container->get('loader.resource_loader')->load($locator, $linenum);
        $runner     = $container->get('runner.specification');
        $dispatcher = $container->get('event_dispatcher');
        $startTime  = microtime(true);
        $result     = 0;

        $dispatcher->dispatch('beforeSuite', new Event\SuiteEvent($suite));

        foreach ($suite->getSpecifications() as $spec) {
            $result = max($result, $runner->run($spec));
        }

        $dispatcher->dispatch('afterSuite', new Event\SuiteEvent(
            $suite, microtime(true) - $startTime, $result
        ));

        return $result;
    }
}
