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

use PhpSpec\ServiceContainer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
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
                    new InputOption('suite', 's', InputOption::VALUE_REQUIRED, 'Suite')
                ))
            ->setDescription('Creates a specification for a class')
            ->setHelp(<<<EOF
The <info>%command.name%</info> command creates a specification for a class:

  <info>php %command.full_name% ClassName</info>

Will generate a specification ClassNameSpec in the spec directory.

  <info>php %command.full_name% Namespace/ClassName</info>

Will generate a specification ClassNameSpec in the <info>spec_path</info>
as specified in the suite configuration.

  <info>php %command.full_name% Namespace/ClassName --suite=<suite_name></info>

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
        $classname = $input->getArgument('class');
        if ($suite = $input->getOption('suite')) {
            return $this->executeWithSpecifiedSuite($suite, $classname);
        }

        return $this->executeWithoutSuite($classname);
    }

    /**
     * @return ServiceContainer
     */
    private function getConfiguredContainer()
    {
        $container = $this->getApplication()->getContainer();
        $container->configure();
        return $container;
    }

    /**
     * @param  string $classname
     *
     * @return mixed
     */
    private function executeWithoutSuite($classname)
    {
        $container = $this->getConfiguredContainer();
        $resource = $container->get('locator.resource_manager')->createResource($classname);
        return $container->get('code_generator')->generate($resource, 'specification');
    }

    /**
     * @param  string $suite
     * @param  string $classname
     *
     * @return mixed
     */
    private function executeWithSpecifiedSuite($suite, $classname)
    {
        $container = $this->getConfiguredContainer();
        if (!$container->has($suiteLocatorId = sprintf('locator.locators.%s_suite', $suite))) {
            throw new InvalidOptionException(sprintf('Invalid suite specified: `%s`', $suite));
        }

        $locator = $container->get($suiteLocatorId);
        return $container->get('code_generator')->generate($locator->createResource($classname), 'specification');
    }
}
