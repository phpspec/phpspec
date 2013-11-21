<?php

namespace PhpSpec\Console;

use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\EventDispatcher\EventDispatcher;

use PhpSpec\ServiceContainer;
use PhpSpec\Console;
use PhpSpec\Locator;
use PhpSpec\Loader;
use PhpSpec\Wrapper;
use PhpSpec\Listener;
use PhpSpec\Formatter;
use PhpSpec\Runner;
use PhpSpec\CodeGenerator;
use PhpSpec\Extension;

use PhpSpec\ServiceContainer\Assembler;

use RuntimeException;

class Application extends BaseApplication
{
    private $container;

    public function __construct($version)
    {
        $this->setupContainer($this->container = new ServiceContainer);
        
        parent::__construct('phpspec', $version);
    }

    public function getContainer()
    {
        return $this->container;
    }

    public function doRun(InputInterface $input, OutputInterface $output)
    {
        $this->container->set('console.input', $input);
        $this->container->set('console.output', $output);
        $this->container->set('console.helpers', $this->getHelperSet());

        $this->fixDefinitions();

        return parent::doRun($input, $output);
    }
    
    protected function fixDefinitions()
    {
        $description = 'Do not ask any interactive question (disables code generation).';
        
        $definition = $this->getDefaultInputDefinition();
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
         
        $definition->setOptions($options);
        $this->setDefinition($definition);
    }
    
    protected function getCommandName(InputInterface $input)
    {
        $name = parent::getCommandName($input);
        
        if (!$name) {
            $name = 'run';
            parent::getDefinition()->setArguments();
        }
        
        return $name;
    }
    
    public function getDefaultCommands()
    {
        $commands = $this->container->getByPrefix('console.commands');
        return array_merge(parent::getDefaultCommands(), $commands);
    }

    protected function setupContainer(ServiceContainer $container)
    {
        $assember = new Assembler($container);
        $assember->assemble();

        $this->loadConfigurationFile($container);
    }

    protected function loadConfigurationFile(ServiceContainer $container)
    {
        $config = array();
        if (file_exists($path = 'phpspec.yml')) {
            $config = Yaml::parse(file_get_contents($path));
        } elseif (file_exists($path = 'phpspec.yml.dist')) {
            $config = Yaml::parse(file_get_contents($path));
        }

        $config = $config ?: array();

        foreach ($config as $key => $val) {
            if ('extensions' === $key) {
                foreach ($val as $class) {
                    $extension = new $class;

                    if (!$extension instanceof Extension\ExtensionInterface) {
                        throw new RuntimeException(sprintf(
                            'Extension class must implement ExtensionInterface. But `%s` is not.',
                            $class
                        ));
                    }

                    $extension->load($container);
                }

                continue;
            }

            $container->setParam($key, $val);
        }
    }
}
