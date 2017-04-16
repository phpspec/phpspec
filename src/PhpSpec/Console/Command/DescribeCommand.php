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
 * Command line command responsible to signal to generators we will need to
 * generate a new spec
 *
 * @Internal
 */
final class DescribeCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('describe')
            ->setDefinition(array(
                    new InputArgument('class', InputArgument::REQUIRED, 'Class to describe'),
                ))
            ->setDescription('Creates a specification for a class')
            ->addOption('confirm', null, InputOption::VALUE_NONE, 'Ask for confirmation before creating spec')
            ->setHelp(<<<EOF
The <info>%command.name%</info> command creates a specification for a class:

  <info>php %command.full_name% ClassName</info>

Will generate a specification ClassNameSpec in the spec directory.

  <info>php %command.full_name% Namespace/ClassName</info>

Will generate a namespaced specification Namespace\ClassNameSpec.
Note that / is used as the separator. To use \ it must be quoted:

  <info>php %command.full_name% "Namespace\ClassName"</info>

EOF
            )
        ;
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int|null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $container = $this->getApplication()->getContainer();
        $container->configure();

        $classname = $input->getArgument('class');

        if (!$this->confirm($input, $container, $classname)) {
            return;
        }

        $resource  = $container->get('locator.resource_manager')->createResource($classname);

        $container->get('code_generator')->generate($resource, 'specification');
    }

    /**
     * @param InputInterface $input
     * @param $container
     * @param $classname
     * @return bool
     */
    private function confirm(InputInterface $input, $container, $classname)
    {
        if (!$input->getOption('confirm')) {
            return true;
        }

        $question = sprintf('Do you want to generate a specification for %s? (Y/n)', $classname);

        if ($container->get('console.io')->askConfirmation($question, true)) {
            return true;
        }

        return false;
    }
}
