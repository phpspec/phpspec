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
        $pattern   = "/^[a-zA-Z0-9_\/\\\\]+$/";

        if (!preg_match($pattern, $classname)) {
            $output->writeln(sprintf(
                "\n<error>String \"%s\" is not a valid class path.</error>\n\n<info>Please see reference document: <value>https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-0.md</value>.</info>",
                $classname
            ));

            return 1;
        }

        $resource = $container->get('locator.resource_manager')->createResource($classname);

        $container->get('code_generator')->generate($resource, 'specification');
    }
}
