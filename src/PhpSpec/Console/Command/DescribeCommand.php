<?php

namespace PhpSpec\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DescribeCommand extends Command
{
    public function __construct()
    {
        parent::__construct('describe');

        $this->setDefinition(array(
            new InputArgument('class', InputArgument::REQUIRED, 'Class to describe'),
        ));
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $container = $this->getApplication()->getContainer();
        $container->configure();

        $classname = $input->getArgument('class');
        $resource  = $container->get('locator.resource_manager')->createResource($classname);

        $container->get('code_generator')->generate($resource, 'specification');
    }
}
