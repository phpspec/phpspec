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

namespace PhpSpec\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Main command, responsible for running the specs
 */
class PhpSpecCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('phpspec')
            ->setDefinition(array(
                    new InputArgument('spec', InputArgument::OPTIONAL, 'Specs to run'),
                    new InputArgument('bc_layer', InputArgument::OPTIONAL, 'BC layer to allow using "phpspec run"'),
                    new InputOption('format', 'f', InputOption::VALUE_REQUIRED, 'Formatter'),
                    new InputOption('stop-on-failure', null , InputOption::VALUE_NONE, 'Stop on failure'),
                    new InputOption('no-code-generation', null , InputOption::VALUE_NONE, 'Do not prompt for missing method/class generation'),
                    new InputOption('describe', null, InputOption::VALUE_REQUIRED, 'Creates a specification for a given class')
                ))
            ->setDescription('Runs specifications')
            ->setHelp(<<<EOF
The <info>%command.name%</info> command runs specifications:

  <info>phpspec</info>

Will run all the specifications in the spec directory.

  <info>phpspec spec/ClassNameSpec.php</info>

Will run only the ClassNameSpec.

By default, you will be asked whether missing methods and classes should
be generated. You can suppress these prompts and automatically choose not
to generate code with:

  <info>phpspec --no-code-generation</info>

You can choose to stop on failure and not attempt to run the remaining
specs with:

  <info>phpspec --stop-on-failure</info>

You can choose the output format with the format option e.g.:

  <info>phpspec --format=dot</info>

The available formatters are:

   progress (default)
   html
   pretty
   junit
   dot

You can also initialize a specification for a class. This will not run the specs:

  <info>phpspec --describe ClassName</info>

Will generate a specification ClassNameSpec in the spec directory.

  <info>phpspec --describe Namespace/ClassName</info>

Will generate a namespaced specification Namespace\ClassNameSpec.
Note that / is used as the separator. To use \ it must be quoted:

  <info>phpspec --describe "Namespace\ClassName"</info>
EOF
            )
        ;
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $bcLayer = $input->getArgument('bc_layer');

        if ('describe' === $input->getArgument('spec') && $bcLayer) {
            $input->setOption('describe', $bcLayer);
            $input->setArgument('bc_layer', null);
            $input->setArgument('spec', null);

            $output->writeln('<error>The command <comment>phpspec describe ClassName</comment> is deprecated and support will be removed.</error>');
            $output->writeln('<error>Please use <info>phpspec --describe ClassName</info> instead.</error>');

            return;
        }

        if ('run' === $input->getArgument('spec')) {
            $input->setArgument('spec', $bcLayer);
            $input->setArgument('bc_layer', null);

            $output->writeln('<error>The command <comment>phpspec run</comment> is deprecated and support will be removed.</error>');
            $output->writeln('<error>Please use <info>phpspec</info> instead.</error>');
        }
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return mixed
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $container = $this->getApplication()->getContainer();
        $container->setParam('formatter.name',
            $input->getOption('format') ?: $container->getParam('formatter.name')
        );
        $container->configure();

        $classname = $input->getOption('describe');

        if ($classname) {
            $resource  = $container->get('locator.resource_manager')->createResource($classname);

            $container->get('code_generator')->generate($resource, 'specification');

            return 0;
        }

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
