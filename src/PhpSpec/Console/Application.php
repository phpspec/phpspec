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

use Interop\Container\ContainerInterface;
use PhpSpec\Container\CompositeContainer;
use PhpSpec\Container\ContainerBuilder;
use PhpSpec\Loader\StreamWrapper;
use PhpSpec\Process\Context\JsonExecutionContext;
use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;
use PhpSpec\Container\ServiceContainer;
use PhpSpec\Container\ServiceContainer\ConfigObject;
use PhpSpec\Extension;
use RuntimeException;

/**
 * The command line application entry point
 */
class Application extends BaseApplication
{
    /**
     * @var CompositeContainer
     */
    private $compositeContainer;

    /**
     * @param string $version
     */
    public function __construct($version)
    {
        $containerBuilder = new ContainerBuilder();
        $this->compositeContainer = $containerBuilder->buildContainer();
        parent::__construct('phpspec', $version);
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
        $this->compositeContainer->getRegistry()->set('console.input', $input);
        $this->compositeContainer->getRegistry()->set('console.output', $output);
        $this->compositeContainer->getRegistry()->set('console.helper_set', $helperSet);

        $this->compositeContainer->getRegistry()->setShared('process.executioncontext', function () {
            return JsonExecutionContext::fromEnv($_SERVER);
        });

        $this->loadConfigurationFile($input, $this->compositeContainer->getConfigObject(), $this->compositeContainer->getContainerPassedToExtensions());

        foreach ($this->compositeContainer->getServiceLocator()->getByPrefix('console.commands') as $command) {
            $this->add($command);
        }

        $this->setDispatcher($this->compositeContainer->getServiceLocator()->get('console_event_dispatcher'));

        $this->compositeContainer->getServiceLocator()->get('console.io')->setConsoleWidth($this->getTerminalWidth());

        StreamWrapper::reset();
        foreach ($this->compositeContainer->getServiceLocator()->getByPrefix('loader.resource_loader.spec_transformer') as $transformer) {
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
     * @param InputInterface     $input
     * @param ConfigObject       $configObject
     * @param CompositeContainer $containerPassedToExtensions
     *
     * @throws \RuntimeException
     */
    protected function loadConfigurationFile(InputInterface $input, ConfigObject $configObject, CompositeContainer $containerPassedToExtensions)
    {
        $config = $this->parseConfigurationFile($input);

        foreach ($config as $key => $val) {
            if ('extensions' === $key && is_array($val)) {
                foreach ($val as $class) {
                    $extension = new $class();

                    if (!$extension instanceof Extension) {
                        throw new RuntimeException(sprintf(
                            'Extension class must implement PhpSpec\Extension. But `%s` is not.',
                            $class
                        ));
                    }

                    $containerReceived = $extension->load($containerPassedToExtensions);
                    if ($containerReceived instanceof ContainerInterface) {
                        $containerPassedToExtensions->addPriorityContainer($containerReceived);
                    }
                }
            } else {
                $configObject->setParam($key, $val);
            }
        }
    }

    /**
     * @param InputInterface $input
     *
     * @return array
     *
     * @throws \RuntimeException
     */
    protected function parseConfigurationFile(InputInterface $input)
    {
        $paths = array('phpspec.yml','phpspec.yml.dist');

        if ($customPath = $input->getParameterOption(array('-c','--config'))) {
            if (!file_exists($customPath)) {
                throw new RuntimeException('Custom configuration file not found at '.$customPath);
            }
            $paths = array($customPath);
        }

        $config = $this->extractConfigFromFirstParsablePath($paths);

        if ($homeFolder = getenv('HOME')) {
            $config = array_replace_recursive($this->parseConfigFromExistingPath($homeFolder.'/.phpspec.yml'), $config);
        }

        return $config;
    }

    /**
     * @param array $paths
     *
     * @return array
     */
    private function extractConfigFromFirstParsablePath(array $paths)
    {
        foreach ($paths as $path) {
            $config = $this->parseConfigFromExistingPath($path);
            if (!empty($config)) {
                return $this->addPathsToEachSuiteConfig(dirname($path), $config);
            }
        }

        return array();
    }

    /**
     * @param string $path
     *
     * @return array
     */
    private function parseConfigFromExistingPath($path)
    {
        if (!file_exists($path)) {
            return array();
        }

        return Yaml::parse(file_get_contents($path));
    }

    /**
     * @param string $configDir
     * @param array $config
     *
     * @return array
     */
    private function addPathsToEachSuiteConfig($configDir, $config)
    {
        if (isset($config['suites']) && is_array($config['suites'])) {
            foreach ($config['suites'] as $suiteKey => $suiteConfig) {
                $config['suites'][$suiteKey] = str_replace('%paths.config%', $configDir, $suiteConfig);
            }
        }

        return $config;
    }

    /**
     * @return CompositeContainer
     */
    public function getCompositeContainer()
    {
        return $this->compositeContainer;
    }
}
