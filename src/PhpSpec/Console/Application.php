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

use PhpSpec\Console\Prompter\Factory;
use PhpSpec\Process\Context\JsonExecutionContext;
use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;
use PhpSpec\ServiceContainer;
use PhpSpec\Extension;
use RuntimeException;

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
        $this->container->set('console.input', $input);
        $this->container->set('console.output', $output);
        $this->container->setShared('console.prompter.factory', function ($c) use ($helperSet) {
            return new Factory(
                $c->get('console.input'),
                $c->get('console.output'),
                $helperSet
            );
        });

        $this->container->setShared('process.executioncontext', function () {
            return JsonExecutionContext::fromEnv($_SERVER);
        });

        $assembler = new ContainerAssembler();
        $assembler->build($this->container);

        $this->loadConfigurationFile($input, $this->container);

        foreach ($this->container->getByPrefix('console.commands') as $command) {
            $this->add($command);
        }

        $this->setDispatcher($this->container->get('console_event_dispatcher'));

        $this->container->get('console.io')->setConsoleWidth($this->getTerminalWidth());

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
     * @param InputInterface   $input
     * @param ServiceContainer $container
     *
     * @throws \RuntimeException
     */
    protected function loadConfigurationFile(InputInterface $input, ServiceContainer $container)
    {
        $config = $this->parseConfigurationFile($input);

        foreach ($config as $key => $val) {
            if ('extensions' === $key && is_array($val)) {
                foreach ($val as $class) {
                    $extension = new $class();

                    if (!$extension instanceof Extension\ExtensionInterface) {
                        throw new RuntimeException(sprintf(
                            'Extension class must implement ExtensionInterface. But `%s` is not.',
                            $class
                        ));
                    }

                    $extension->load($container);
                }
            } else {
                $container->setParam($key, $val);
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
}
