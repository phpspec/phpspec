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

use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\EventDispatcher\EventDispatcher;

use PhpSpec\ServiceContainer;
use PhpSpec\Extension;

use PhpSpec\CodeGenerator;
use PhpSpec\Formatter as SpecFormatter;
use PhpSpec\Listener;
use PhpSpec\Loader;
use PhpSpec\Locator;
use PhpSpec\Runner;
use PhpSpec\Wrapper;
use PhpSpec\Config\OptionsConfig;

use RuntimeException;

/**
 * The command line application entry point
 */
class Application extends BaseApplication
{
    /**
     * @var \PhpSpec\ServiceContainer
     */
    private $container;

    /**
     * @param string $version
     */
    public function __construct($version)
    {
        $this->container = new ServiceContainer;
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
        $this->container->set('console.input', $input);
        $this->container->set('console.output', $output);
        $this->container->set('console.helpers', $this->getHelperSet());

        $this->setupContainer($this->container);
        $this->loadConfigurationFile($input, $this->container);

        foreach ($this->container->getByPrefix('console.commands') as $command) {
            $this->add($command);
        }

        return $this->container->get('console.result_converter')->convert(
            parent::doRun($input, $output)
        );
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
     */
    protected function setupContainer(ServiceContainer $container)
    {
        $this->setupIO($container);
        $this->setupEventDispatcher($container);
        $this->setupGenerators($container);
        $this->setupPresenter($container);
        $this->setupLocator($container);
        $this->setupLoader($container);
        $this->setupFormatter($container);
        $this->setupRunner($container);
        $this->setupCommands($container);
        $this->setupResultConverter($container);

    }

    protected function setupIO(ServiceContainer $container)
    {
        $container->setShared('console.io', function ($c) {
            return new IO(
                $c->get('console.input'),
                $c->get('console.output'),
                $c->get('console.helpers'),
                new OptionsConfig(
                    $c->getParam('stop_on_failure', false),
                    $c->getParam('code_generation', true)
                )
            );
        });
    }

    protected function setupResultConverter(ServiceContainer $container)
    {
        $container->setShared('console.result_converter', function ($c) {
            return new ResultConverter;
        });
    }

    protected function setupCommands(ServiceContainer $container)
    {
        $container->setShared('console.commands.run', function ($c) {
            return new Command\RunCommand;
        });

        $container->setShared('console.commands.describe', function ($c) {
            return new Command\DescribeCommand;
        });
    }

    /**
     * @param ServiceContainer $container
     */
    protected function setupEventDispatcher(ServiceContainer $container)
    {
        $container->setShared('event_dispatcher', function ($c) {
            $dispatcher = new EventDispatcher;

            array_map(
                array($dispatcher, 'addSubscriber'),
                $c->getByPrefix('event_dispatcher.listeners')
            );

            return $dispatcher;
        });

        $container->setShared('event_dispatcher.listeners.stats', function ($c) {
            return new Listener\StatisticsCollector;
        });
        $container->setShared('event_dispatcher.listeners.class_not_found', function ($c) {
            return new Listener\ClassNotFoundListener(
                $c->get('console.io'),
                $c->get('locator.resource_manager'),
                $c->get('code_generator')
            );
        });
        $container->setShared('event_dispatcher.listeners.method_not_found', function ($c) {
            return new Listener\MethodNotFoundListener(
                $c->get('console.io'),
                $c->get('locator.resource_manager'),
                $c->get('code_generator')
            );
        });
        $container->setShared('event_dispatcher.listeners.stop_on_failure', function ($c) {
            return new Listener\StopOnFailureListener(
                $c->get('console.io')
            );
        });
    }

    /**
     * @param ServiceContainer $container
     */
    protected function setupGenerators(ServiceContainer $container)
    {
        $container->setShared('code_generator', function ($c) {
            $generator = new CodeGenerator\GeneratorManager;

            array_map(
                array($generator, 'registerGenerator'),
                $c->getByPrefix('code_generator.generators')
            );

            return $generator;
        });

        $container->set('code_generator.generators.specification', function ($c) {
            return new CodeGenerator\Generator\SpecificationGenerator(
                $c->get('console.io'),
                $c->get('code_generator.templates')
            );
        });
        $container->set('code_generator.generators.class', function ($c) {
            return new CodeGenerator\Generator\ClassGenerator(
                $c->get('console.io'),
                $c->get('code_generator.templates')
            );
        });
        $container->set('code_generator.generators.method', function ($c) {
            return new CodeGenerator\Generator\MethodGenerator(
                $c->get('console.io'),
                $c->get('code_generator.templates')
            );
        });

        $container->setShared('code_generator.templates', function ($c) {
            $renderer = new CodeGenerator\TemplateRenderer;
            $renderer->setLocations($c->getParam('code_generator.templates.paths', array()));

            return $renderer;
        });

        if (!empty($_SERVER['HOMEDRIVE']) && !empty($_SERVER['HOMEPATH'])) {
            $home = $_SERVER['HOMEDRIVE'] . $_SERVER['HOMEPATH'];
        } else {
            $home = $_SERVER['HOME'];
        }

        $container->setParam('code_generator.templates.paths', array(
            rtrim(getcwd(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'.phpspec',
            rtrim($home, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'.phpspec',
        ));
    }

    /**
     * @param ServiceContainer $container
     */
    protected function setupPresenter(ServiceContainer $container)
    {
        $container->setShared('formatter.presenter', function ($c) {
            return new SpecFormatter\Presenter\TaggedPresenter($c->get('formatter.presenter.differ'));
        });

        $container->setShared('formatter.presenter.differ', function ($c) {
            $differ = new SpecFormatter\Presenter\Differ\Differ;

            array_map(
                array($differ, 'addEngine'),
                $c->getByPrefix('formatter.presenter.differ.engines')
            );

            return $differ;
        });

        $container->set('formatter.presenter.differ.engines.string', function ($c) {
            return new SpecFormatter\Presenter\Differ\StringEngine;
        });
        $container->set('formatter.presenter.differ.engines.array', function ($c) {
            return new SpecFormatter\Presenter\Differ\ArrayEngine;
        });
    }

    /**
     * @param ServiceContainer $container
     */
    protected function setupLocator(ServiceContainer $container)
    {
        $container->setShared('locator.resource_manager', function ($c) {
            $manager = new Locator\ResourceManager();

            array_map(
                array($manager, 'registerLocator'),
                $c->getByPrefix('locator.locators')
            );

            return $manager;
        });

        $container->addConfigurator(function ($c) {
            $suites = $c->getParam('suites', array('main' => ''));

            foreach ($suites as $name => $suite) {
                $suite      = is_array($suite) ? $suite : array('namespace' => $suite);
                $srcNS      = isset($suite['namespace']) ? $suite['namespace'] : '';
                $specPrefix = isset($suite['spec_prefix']) ? $suite['spec_prefix'] : 'spec';
                $srcPath    = isset($suite['src_path']) ? $suite['src_path'] : 'src';
                $specPath   = isset($suite['spec_path']) ? $suite['spec_path'] : '.';
                $psr4prefix   = isset($suite['psr4_prefix']) ? $suite['psr4_prefix'] : null;

                if (!is_dir($srcPath)) {
                    mkdir($srcPath, 0777, true);
                }
                if (!is_dir($specPath)) {
                    mkdir($specPath, 0777, true);
                }

                $c->set(sprintf('locator.locators.%s_suite', $name),
                    function ($c) use ($srcNS, $specPrefix, $srcPath, $specPath, $psr4prefix) {
                        return new Locator\PSR0\PSR0Locator($srcNS, $specPrefix, $srcPath, $specPath, null, $psr4prefix);
                    }
                );
            }
        });
    }

    /**
     * @param ServiceContainer $container
     */
    protected function setupLoader(ServiceContainer $container)
    {
        $container->setShared('loader.resource_loader', function ($c) {
            return new Loader\ResourceLoader($c->get('locator.resource_manager'));
        });
    }

    /**
     * @param ServiceContainer $container
     *
     * @throws \RuntimeException
     */
    protected function setupFormatter(ServiceContainer $container)
    {
        $container->set('formatter.formatters.progress', function ($c) {
            return new SpecFormatter\ProgressFormatter($c->get('formatter.presenter'), $c->get('console.io'), $c->get('event_dispatcher.listeners.stats'));
        });
        $container->set('formatter.formatters.pretty', function ($c) {
            return new SpecFormatter\PrettyFormatter($c->get('formatter.presenter'), $c->get('console.io'), $c->get('event_dispatcher.listeners.stats'));
        });
        $container->set('formatter.formatters.junit', function ($c) {
            return new SpecFormatter\JUnitFormatter($c->get('formatter.presenter'), $c->get('console.io'), $c->get('event_dispatcher.listeners.stats'));
        });
        $container->set('formatter.formatters.dot', function ($c) {
            return new SpecFormatter\DotFormatter($c->get('formatter.presenter'), $c->get('console.io'), $c->get('event_dispatcher.listeners.stats'));
        });
        $container->set('formatter.formatters.html', function ($c) {
            $io = new SpecFormatter\Html\IO;
            $template = new SpecFormatter\Html\Template($io);
            $factory = new SpecFormatter\Html\ReportItemFactory($template);
            $presenter = new SpecFormatter\Html\HtmlPresenter($c->get('formatter.presenter.differ'));

            return new SpecFormatter\HtmlFormatter($factory, $presenter, $io, $c->get('event_dispatcher.listeners.stats'));
        });
        $container->set('formatter.formatters.h', function ($c) {
            return $c->get('formatter.formatters.html');
        });

        $container->addConfigurator(function ($c) {
            $formatterName = $c->getParam('formatter.name', 'progress');

            $c->get('console.output')->setFormatter(new \PhpSpec\Console\Formatter(
                $c->get('console.output')->isDecorated()
            ));

            try {
                $formatter = $c->get('formatter.formatters.'.$formatterName);
            } catch (\InvalidArgumentException $e) {
                throw new \RuntimeException(sprintf('Formatter not recognised: "%s"', $formatterName));
            }

            $c->set('event_dispatcher.listeners.formatter', $formatter);
        });
    }

    /**
     * @param ServiceContainer $container
     */
    protected function setupRunner(ServiceContainer $container)
    {
        $container->setShared('runner.suite', function ($c) {
            return new Runner\SuiteRunner(
                $c->get('event_dispatcher'),
                $c->get('runner.specification')
            );
        });

        $container->setShared('runner.specification', function ($c) {
            return new Runner\SpecificationRunner(
                $c->get('event_dispatcher'),
                $c->get('runner.example')
            );
        });

        $container->setShared('runner.example', function ($c) {
            $runner = new Runner\ExampleRunner(
                $c->get('event_dispatcher'),
                $c->get('formatter.presenter')
            );

            array_map(
                array($runner, 'registerMaintainer'),
                $c->getByPrefix('runner.maintainers')
            );

            return $runner;
        });

        $container->set('runner.maintainers.errors', function ($c) {
            return new Runner\Maintainer\ErrorMaintainer(
                $c->getParam('runner.maintainers.errors.level', E_ALL ^ E_STRICT)
            );
        });
        $container->set('runner.maintainers.collaborators', function ($c) {
            return new Runner\Maintainer\CollaboratorsMaintainer($c->get('unwrapper'));
        });
        $container->set('runner.maintainers.let_letgo', function ($c) {
            return new Runner\Maintainer\LetAndLetgoMaintainer;
        });
        $container->set('runner.maintainers.matchers', function ($c) {
            return new Runner\Maintainer\MatchersMaintainer(
                $c->get('formatter.presenter'),
                $c->get('unwrapper')
            );
        });
        $container->set('runner.maintainers.subject', function ($c) {
            return new Runner\Maintainer\SubjectMaintainer(
                $c->get('formatter.presenter'),
                $c->get('unwrapper'),
                $c->get('event_dispatcher')
            );
        });

        $container->setShared('unwrapper', function ($c) {
            return new Wrapper\Unwrapper;
        });
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
                    $extension = new $class;

                    if (!$extension instanceof Extension\ExtensionInterface) {
                        throw new RuntimeException(sprintf(
                            'Extension class must implement ExtensionInterface. But `%s` is not.',
                            $class
                        ));
                    }

                    $extension->load($this->container);
                }
            } else {
                $container->setParam($key, $val);
            }
        }
    }

    /**
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

        foreach ($paths as $path) {
            if ($path && file_exists($path) && $config = Yaml::parse(file_get_contents($path))) {
                return $config;
            }
        }

        return array();
    }
}
