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
use PhpSpec\Process\Context\JsonExecutionContext;
use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use PhpSpec\Container\ServiceContainer;

/**
 * The command line application entry point
 */
class Application extends BaseApplication
{
    /**
     * @var ServiceContainer
     */
    private $container;

    /**
     * @param string $version
     */
    public function __construct($version)
    {
        $this->container = new ServiceContainer();
        parent::__construct('phpspec', $version);
    }

    /**
     * @return ServiceContainer
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
        $helperSet = $this->getHelperSet();

        $assembler = new ServiceContainerConfigurer();
        $assembler->build($this->container);

        $this->container->get('phpspec.config-manager')->setInput($input);
        $this->container->set('console.input', $input);
        $this->container->set('console.output', $output);
        $this->container->set('console.helper_set', $helperSet);

        $this->loadExtensions($this->container);

        $this->container->setShared('process.executioncontext', function () {
            return JsonExecutionContext::fromEnv($_SERVER);
        });

        foreach ($this->container->getByPrefix('console.commands') as $command) {
            $this->add($command);
        }

        $this->setDispatcher($this->container->get('console_event_dispatcher'));

        $this->container->get('console.io')->setConsoleWidth($this->getTerminalWidth());

        StreamWrapper::reset();
        foreach ($this->container->getByPrefix('loader.resource_loader.spec_transformer') as $transformer) {
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
     * @param ServiceContainer $container
     *
     * @throws \RuntimeException
     */
    private function loadExtensions(ServiceContainer $container)
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
}
