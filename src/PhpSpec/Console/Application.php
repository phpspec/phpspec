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

use PhpSpec\Container\ServiceContainerConfigurer;
use PhpSpec\Extension;
use PhpSpec\Loader\StreamWrapper;
use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use PhpSpec\Console\Manager as ConsoleManager;
use UltraLite\Container\Container;
use Interop\Container\ContainerInterface;

/**
 * The command line application entry point
 */
class Application extends BaseApplication
{
    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @param string $version
     */
    public function __construct($version)
    {
        $this->container = new Container();
        parent::__construct('phpspec', $version);
    }

    /**
     * @return ContainerInterface
     */
    public function getContainer()
    {
        return $this->container;
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int
     */
    public function doRun(InputInterface $input, OutputInterface $output)
    {
        $assembler = new ServiceContainerConfigurer();
        $assembler->build($this->container);

        $this->container->get('phpspec.config-manager')->setInput($input);
        $consoleManager = $this->container->get('phpspec.console-manager');
        $this->configureConsoleManager($consoleManager, $input, $output);

        $this->loadExtensions($this->container);

        foreach ($this->container->get('phpspec.console.commands') as $command) {
            $this->add($command);
        }

        $this->setDispatcher($this->container->get('console_event_dispatcher'));

        $this->container->get('console.io')->setConsoleWidth($this->getTerminalWidth());

        StreamWrapper::reset();
        foreach ($this->container->get('phpspec.spec-transformers') as $transformer) {
            StreamWrapper::addTransformer($transformer);
        }
        StreamWrapper::register();

        return parent::doRun($input, $output);
    }

    /**
     * Fixes an issue with definitions of the no-interaction option not being
     * completely shown in some cases
     */
    protected function getDefaultInputDefinition()
    {
        $description = 'Do not ask any interactive question (disables code generation).';

        $definition = parent::getDefaultInputDefinition();
        $options = $definition->getOptions();

        if (array_key_exists('no-interaction', $options)) {
            $option = $options['no-interaction'];
            $options['no-interaction'] = new InputOption(
                $option->getName(),
                $option->getShortcut(),
                InputOption::VALUE_NONE,
                $description
            );
        }

        $options['config'] = new InputOption(
            'config',
            'c',
            InputOption::VALUE_REQUIRED,
            'Specify a custom location for the configuration file'
        );

        $definition->setOptions($options);

        return $definition;
    }

    /**
     * @param ContainerInterface $container
     *
     * @throws \RuntimeException
     */
    private function loadExtensions(ContainerInterface $container)
    {
        foreach ($container->get('phpspec.config-manager')->optionsConfig()->getExtensions() as $class) {
            $extension = new $class();

            if (!$extension instanceof Extension) {
                throw new \RuntimeException(sprintf(
                    'Extension class must implement PhpSpec\Extension. But `%s` is not.',
                    $class
                ));
            }

            $extension->load($container);
        }
    }

    private function configureConsoleManager(ConsoleManager $consoleManager, InputInterface $input, OutputInterface $output)
    {
        $consoleManager->setInput($input);
        $consoleManager->setOutput($output);
        $consoleManager->setQuestionHelper($this->getHelperSet()->get('question'));
    }
}
