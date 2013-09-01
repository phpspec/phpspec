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
        $this->setupConsole($container);
        $this->setupEventDispatcher($container);
        $this->setupGenerators($container);
        $this->setupPresenter($container);
        $this->setupLocator($container);
        $this->setupLoader($container);
        $this->setupFormatter($container);
        $this->setupRunner($container);

        $this->loadConfigurationFile($container);
    }

    protected function setupConsole(ServiceContainer $container)
    {
        $container->setShared('console.io', function($c) {
            return new Console\IO(
                $c->get('console.input'),
                $c->get('console.output'),
                $c->get('console.helpers')
            );
        });

        $container->setShared('html.io', function($c) {
            return new Formatter\Html\IO;
        });

        $container->setShared('console.commands.run', function($c) {
            return new Console\Command\RunCommand;
        });

        $container->setShared('console.commands.describe', function($c) {
            return new Console\Command\DescribeCommand;
        });
    }

    protected function setupEventDispatcher(ServiceContainer $container)
    {
        $container->setShared('event_dispatcher', function($c) {
            $dispatcher = new EventDispatcher;

            array_map(
                array($dispatcher, 'addSubscriber'),
                $c->getByPrefix('event_dispatcher.listeners')
            );

            return $dispatcher;
        });

        $container->setShared('event_dispatcher.listeners.stats', function($c) {
            return new Listener\StatisticsCollector;
        });
        $container->setShared('event_dispatcher.listeners.class_not_found', function($c) {
            return new Listener\ClassNotFoundListener(
                $c->get('console.io'),
                $c->get('locator.resource_manager'),
                $c->get('code_generator')
            );
        });
        $container->setShared('event_dispatcher.listeners.method_not_found', function($c) {
            return new Listener\MethodNotFoundListener(
                $c->get('console.io'),
                $c->get('locator.resource_manager'),
                $c->get('code_generator')
            );
        });
        $container->setShared('event_dispatcher.listeners.stop_on_failure', function($c) {
            return new Listener\StopOnFailureListener(
                $c->get('console.input')
            );
        });
    }

    protected function setupGenerators(ServiceContainer $container)
    {
        $container->setShared('code_generator', function($c) {
            $generator = new CodeGenerator\GeneratorManager;

            array_map(
                array($generator, 'registerGenerator'),
                $c->getByPrefix('code_generator.generators')
            );

            return $generator;
        });

        $container->set('code_generator.generators.specification', function($c) {
            return new CodeGenerator\Generator\SpecificationGenerator(
                $c->get('console.io'),
                $c->get('code_generator.templates')
            );
        });
        $container->set('code_generator.generators.class', function($c) {
            return new CodeGenerator\Generator\ClassGenerator(
                $c->get('console.io'),
                $c->get('code_generator.templates')
            );
        });
        $container->set('code_generator.generators.method', function($c) {
            return new CodeGenerator\Generator\MethodGenerator(
                $c->get('console.io'),
                $c->get('code_generator.templates')
            );
        });

        $container->setShared('code_generator.templates', function($c) {
            $renderer = new CodeGenerator\TemplateRenderer;
            $renderer->setLocations($c->getParam('code_generator.templates.paths', array()));

            return $renderer;
        });

        $container->setParam('code_generator.templates.paths', array(
            rtrim(getcwd(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'.phpspec',
            rtrim($_SERVER['HOME'], DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'.phpspec',
        ));
    }

    protected function setupPresenter(ServiceContainer $container)
    {
        $container->setShared('formatter.presenter', function($c) {
            return new Formatter\Presenter\TaggedPresenter($c->get('formatter.presenter.differ'));
        });

        $container->setShared('formatter.html.presenter', function($c) {
            return new Formatter\Html\HtmlPresenter($c->get('formatter.presenter.differ'));
        });

        $container->setShared('formatter.presenter.differ', function($c) {
            $differ = new Formatter\Presenter\Differ\Differ;

            array_map(
                array($differ, 'addEngine'),
                $c->getByPrefix('formatter.presenter.differ.engines')
            );

            return $differ;
        });

        $container->set('formatter.presenter.differ.engines.string', function($c) {
            return new Formatter\Presenter\Differ\StringEngine;
        });
        $container->set('formatter.presenter.differ.engines.array', function($c) {
            return new Formatter\Presenter\Differ\ArrayEngine;
        });
    }

    protected function setupLocator(ServiceContainer $container)
    {
        $container->setShared('locator.resource_manager', function($c) {
            $manager = new Locator\ResourceManager();

            array_map(
                array($manager, 'registerLocator'),
                $c->getByPrefix('locator.locators')
            );

            return $manager;
        });

        $container->addConfigurator(function($c) {
            $suites = $c->getParam('suites', array('main' => ''));

            foreach ($suites as $name => $suite) {
                $suite      = is_array($suite) ? $suite : array('namespace' => $suite);
                $srcNS      = $suite['namespace'];
                $specPrefix = isset($suite['spec_prefix']) ? $suite['spec_prefix'] : 'spec';
                $srcPath    = isset($suite['src_path']) ? $suite['src_path'] : 'src';
                $specPath   = isset($suite['spec_path']) ? $suite['spec_path'] : '.';

                if (!is_dir($srcPath)) {
                    mkdir($srcPath, 0777, true);
                }
                if (!is_dir($specPath)) {
                    mkdir($specPath, 0777, true);
                }

                $c->set(sprintf('locator.locators.%s_suite', $name),
                    function($c) use($srcNS, $specPrefix, $srcPath, $specPath) {
                        return new Locator\PSR0\PSR0Locator($srcNS, $specPrefix, $srcPath, $specPath);
                    }
                );
            }
        });
    }

    protected function setupLoader(ServiceContainer $container)
    {
        $container->setShared('loader.resource_loader', function($c) {
            return new Loader\ResourceLoader($c->get('locator.resource_manager'));
        });
    }

    protected function setupFormatter(ServiceContainer $container)
    {
        $container->addConfigurator(function($c) {
            switch ($c->getParam('formatter.name', 'progress')) {
                case 'pretty':
                    $formatter = new Formatter\PrettyFormatter;
                    break;
                case 'dot':
                    $formatter = new Formatter\DotFormatter;
                    break;
                case 'nyan':
                    if (class_exists('NyanCat\Scoreboard')) {
                        $formatter = new Formatter\NyanFormatter;
                    } else {
                        throw new RuntimeException(
                            'The Nyan Cat formatter requires whatthejeff/nyancat-scoreboard:~1.1'
                        );
                    }
                    break;
                case 'html':
                case 'h':
                    $template = new Formatter\Html\Template($c->get('html.io'));
                    $factory = new Formatter\Html\ReportItemFactory($template);
                    $formatter = new Formatter\HtmlFormatter($factory);
                    break;
                case 'progress':
                default:
                    $formatter = new Formatter\ProgressFormatter;
                    break;
            }

            if ($formatter instanceof Formatter\HtmlFormatter) {
                $formatter->setIO($c->get('html.io'));
                $formatter->setPresenter($c->get('formatter.html.presenter'));
            } else {
                $formatter->setIO($c->get('console.io'));
                $formatter->setPresenter($c->get('formatter.presenter'));
            }

            $formatter->setStatisticsCollector($c->get('event_dispatcher.listeners.stats'));

            $c->set('event_dispatcher.listeners.formatter', $formatter);
            $c->get('console.output')->setFormatter(new Console\Formatter(
                $c->get('console.output')->isDecorated()
            ));
        });
    }

    protected function setupRunner(ServiceContainer $container)
    {
        $container->setShared('runner.suite', function($c) {
            return new Runner\SuiteRunner(
                $c->get('event_dispatcher'),
                $c->get('runner.specification')
            );
        });
        
        $container->setShared('runner.specification', function($c) {
            return new Runner\SpecificationRunner(
                $c->get('event_dispatcher'),
                $c->get('runner.example')
            );
        });

        $container->setShared('runner.example', function($c) {
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

        $container->set('runner.maintainers.errors', function($c) {
            return new Runner\Maintainer\ErrorMaintainer(
                $c->getParam('runner.maintainers.errors.level', E_ALL ^ E_STRICT)
            );
        });
        $container->set('runner.maintainers.collaborators', function($c) {
            return new Runner\Maintainer\CollaboratorsMaintainer($c->get('unwrapper'));
        });
        $container->set('runner.maintainers.let_letgo', function($c) {
            return new Runner\Maintainer\LetAndLetgoMaintainer;
        });
        $container->set('runner.maintainers.matchers', function($c) {
            return new Runner\Maintainer\MatchersMaintainer(
                $c->get('formatter.presenter'),
                $c->get('unwrapper')
            );
        });
        $container->set('runner.maintainers.subject', function($c) {
            return new Runner\Maintainer\SubjectMaintainer(
                $c->get('formatter.presenter'),
                $c->get('unwrapper'),
                $c->get('event_dispatcher')
            );
        });

        $container->setShared('unwrapper', function($c) {
            return new Wrapper\Unwrapper;
        });
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
