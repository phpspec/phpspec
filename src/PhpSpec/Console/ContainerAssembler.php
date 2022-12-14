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

use PhpSpec\Console\Command\RunCommand;
use PhpSpec\Console\Command\DescribeCommand;
use PhpSpec\Listener\StatisticsCollector;
use PhpSpec\Listener\ClassNotFoundListener;
use PhpSpec\Listener\CollaboratorNotFoundListener;
use PhpSpec\Listener\CollaboratorMethodNotFoundListener;
use PhpSpec\Listener\NamedConstructorNotFoundListener;
use PhpSpec\Listener\MethodNotFoundListener;
use PhpSpec\Listener\StopOnFailureListener;
use PhpSpec\Listener\RerunListener;
use PhpSpec\Listener\MethodReturnedNullListener;
use PhpSpec\Listener\BootstrapListener;
use PhpSpec\Listener\CurrentExampleListener;
use PhpSpec\CodeGenerator\GeneratorManager;
use PhpSpec\CodeGenerator\Generator\SpecificationGenerator;
use PhpSpec\CodeGenerator\Generator\ValidateClassNameSpecificationGenerator;
use PhpSpec\CodeGenerator\Generator\NewFileNotifyingGenerator;
use PhpSpec\CodeGenerator\Generator\ClassGenerator;
use PhpSpec\CodeGenerator\Generator\InterfaceGenerator;
use PhpSpec\CodeGenerator\Writer\TokenizedCodeWriter;
use PhpSpec\CodeGenerator\Generator\MethodGenerator;
use PhpSpec\CodeGenerator\Generator\MethodSignatureGenerator;
use PhpSpec\CodeGenerator\Generator\ReturnConstantGenerator;
use PhpSpec\CodeGenerator\Generator\NamedConstructorGenerator;
use PhpSpec\CodeGenerator\Generator\OneTimeGenerator;
use PhpSpec\CodeGenerator\Generator\ConfirmingGenerator;
use PhpSpec\CodeGenerator\Generator\PrivateConstructorGenerator;
use PhpSpec\CodeGenerator\TemplateRenderer;
use PhpSpec\Locator\PrioritizedResourceManager;
use PhpSpec\Locator\PSR0\PSR0Locator;
use PhpSpec\Loader\ResourceLoader;
use PhpSpec\Loader\Transformer\TypeHintRewriter;
use PhpSpec\Loader\Transformer\InMemoryTypeHintIndex;
use PhpSpec\Runner\SuiteRunner;
use PhpSpec\Runner\SpecificationRunner;
use PhpSpec\Runner\ExampleRunner;
use PhpSpec\Runner\Maintainer\ErrorMaintainer;
use PhpSpec\Runner\Maintainer\CollaboratorsMaintainer;
use PhpSpec\Runner\Maintainer\LetAndLetgoMaintainer;
use PhpSpec\Runner\Maintainer\MatchersMaintainer;
use PhpSpec\Runner\Maintainer\SubjectMaintainer;
use PhpSpec\Wrapper\Unwrapper;
use PhpSpec\Matcher\IdentityMatcher;
use PhpSpec\Matcher\ComparisonMatcher;
use PhpSpec\Matcher\ThrowMatcher;
use PhpSpec\Matcher\TriggerMatcher;
use PhpSpec\Matcher\TypeMatcher;
use PhpSpec\Matcher\ObjectStateMatcher;
use PhpSpec\Matcher\ScalarMatcher;
use PhpSpec\Matcher\ArrayCountMatcher;
use PhpSpec\Matcher\ArrayKeyMatcher;
use PhpSpec\Matcher\ArrayKeyValueMatcher;
use PhpSpec\Matcher\ArrayContainMatcher;
use PhpSpec\Matcher\StringStartMatcher;
use PhpSpec\Matcher\StringEndMatcher;
use PhpSpec\Matcher\StringRegexMatcher;
use PhpSpec\Matcher\StringContainMatcher;
use PhpSpec\Matcher\TraversableCountMatcher;
use PhpSpec\Matcher\TraversableKeyMatcher;
use PhpSpec\Matcher\TraversableKeyValueMatcher;
use PhpSpec\Matcher\TraversableContainMatcher;
use PhpSpec\Matcher\IterateAsMatcher;
use PhpSpec\Matcher\IterateLikeMatcher;
use PhpSpec\Matcher\StartIteratingAsMatcher;
use PhpSpec\Matcher\ApproximatelyMatcher;
use PhpSpec\Process\ReRunner\OptionalReRunner;
use PhpSpec\Process\ReRunner\CompositeReRunner;
use PhpSpec\Process\ReRunner\PcntlReRunner;
use PhpSpec\Process\ReRunner\ProcOpenReRunner;
use PhpSpec\Process\ReRunner\WindowsPassthruReRunner;
use PhpSpec\CodeAnalysis\MagicAwareAccessInspector;
use PhpSpec\CodeAnalysis\StaticRejectingNamespaceResolver;
use PhpSpec\CodeAnalysis\TokenizedNamespaceResolver;
use PhpSpec\CodeAnalysis\TokenizedTypeHintRewriter;
use PhpSpec\CodeAnalysis\VisibilityAccessInspector;
use PhpSpec\CodeGenerator;
use PhpSpec\Config\OptionsConfig;
use PhpSpec\Console\Assembler\PresenterAssembler;
use PhpSpec\Console\Prompter\Question;
use PhpSpec\Console\Provider\NamespacesAutocompleteProvider;
use PhpSpec\Factory\ReflectionFactory;
use PhpSpec\Formatter as SpecFormatter;
use PhpSpec\Listener;
use PhpSpec\Loader;
use PhpSpec\Locator;
use PhpSpec\Matcher;
use PhpSpec\Message\CurrentExampleTracker;
use PhpSpec\NamespaceProvider\ComposerPsrNamespaceProvider;
use PhpSpec\NamespaceProvider\NamespaceProvider;
use PhpSpec\Process\Prerequisites\SuitePrerequisites;
use PhpSpec\Process\ReRunner;
use PhpSpec\Runner;
use PhpSpec\ServiceContainer\IndexedServiceContainer;
use PhpSpec\Util\ClassFileAnalyser;
use PhpSpec\Util\ClassNameChecker;
use PhpSpec\Util\Filesystem;
use PhpSpec\Util\MethodAnalyser;
use PhpSpec\Util\ReservedWordsMethodNameChecker;
use PhpSpec\Wrapper;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Finder\Finder;
use PhpSpec\Process\Shutdown\Shutdown;

/**
 * @internal
 */
final class ContainerAssembler
{
    
    public function build(IndexedServiceContainer $container): void
    {
        $this->setupParameters($container);
        $this->setupIO($container);
        $this->setupEventDispatcher($container);
        $this->setupConsoleEventDispatcher($container);
        $this->setupGenerators($container);
        $this->setupPresenter($container);
        $this->setupLocator($container);
        $this->setupLoader($container);
        $this->setupFormatter($container);
        $this->setupRunner($container);
        $this->setupCommands($container);
        $this->setupResultConverter($container);
        $this->setupRerunner($container);
        $this->setupMatchers($container);
        $this->setupSubscribers($container);
        $this->setupCurrentExample($container);
        $this->setupShutdown($container);
    }

    private function setupParameters(IndexedServiceContainer $container): void
    {
        $container->setParam(
            'generator.private-constructor.message',
            'Do you want me to make the constructor of {CLASSNAME} private for you?'
        );
    }

    private function setupIO(IndexedServiceContainer $container): void
    {
        if (!$container->has('console.prompter')) {
            $container->define('console.prompter', function ($c) {
                return new Question(
                    $c->get('console.input'),
                    $c->get('console.output'),
                    $c->get('console.helper_set')->get('question')
                );
            });
        }
        $container->define('console.io', function (IndexedServiceContainer $c) {
            return new ConsoleIO(
                $c->get('console.input'),
                $c->get('console.output'),
                new OptionsConfig(
                    $c->getParam('stop_on_failure', false),
                    $c->getParam('code_generation', true),
                    $c->getParam('rerun', true),
                    $c->getParam('fake', false),
                    $c->getParam('bootstrap', false),
                    $c->getParam('verbose', false)
                ),
                $c->get('console.prompter')
            );
        });
        $container->define('util.filesystem', function () {
            return new Filesystem();
        });
        $container->define('console.autocomplete_provider', function (IndexedServiceContainer $container) {
            return new NamespacesAutocompleteProvider(
                new Finder(),
                $container->getByTag('locator.locators')
            );
        });
    }

    private function setupResultConverter(IndexedServiceContainer $container): void
    {
        $container->define('console.result_converter', function () {
            return new ResultConverter();
        });
    }

    private function setupCommands(IndexedServiceContainer $container): void
    {
        $container->define('console.commands.run', function () {
            return new RunCommand();
        }, ['console.commands']);

        $container->define('console.commands.describe', function () {
            return new DescribeCommand();
        }, ['console.commands']);
    }

    
    private function setupConsoleEventDispatcher(IndexedServiceContainer $container): void
    {
        $container->define('console_event_dispatcher', function (IndexedServiceContainer $c) {
            $dispatcher = new EventDispatcher();

            array_map(
                array($dispatcher, 'addSubscriber'),
                $c->getByTag('console_event_dispatcher.listeners')
            );

            return $dispatcher;
        });
    }

    
    private function setupEventDispatcher(IndexedServiceContainer $container): void
    {
        $container->define('event_dispatcher', function () {
            return new EventDispatcher();
        });

        $container->define('event_dispatcher.listeners.stats', function () {
            return new StatisticsCollector();
        }, ['event_dispatcher.listeners']);
        $container->define('event_dispatcher.listeners.class_not_found', function (IndexedServiceContainer $c) {
            return new ClassNotFoundListener(
                $c->get('console.io'),
                $c->get('locator.resource_manager'),
                $c->get('code_generator')
            );
        }, ['event_dispatcher.listeners']);
        $container->define('event_dispatcher.listeners.collaborator_not_found', function (IndexedServiceContainer $c) {
            return new CollaboratorNotFoundListener(
                $c->get('console.io'),
                $c->get('locator.resource_manager'),
                $c->get('code_generator')
            );
        }, ['event_dispatcher.listeners']);
        $container->define('event_dispatcher.listeners.collaborator_method_not_found', function (IndexedServiceContainer $c) {
            return new CollaboratorMethodNotFoundListener(
                $c->get('console.io'),
                $c->get('locator.resource_manager'),
                $c->get('code_generator'),
                $c->get('util.reserved_words_checker')
            );
        }, ['event_dispatcher.listeners']);
        $container->define('event_dispatcher.listeners.named_constructor_not_found', function (IndexedServiceContainer $c) {
            return new NamedConstructorNotFoundListener(
                $c->get('console.io'),
                $c->get('locator.resource_manager'),
                $c->get('code_generator')
            );
        }, ['event_dispatcher.listeners']);
        $container->define('event_dispatcher.listeners.method_not_found', function (IndexedServiceContainer $c) {
            return new MethodNotFoundListener(
                $c->get('console.io'),
                $c->get('locator.resource_manager'),
                $c->get('code_generator'),
                $c->get('util.reserved_words_checker')
            );
        }, ['event_dispatcher.listeners']);
        $container->define('event_dispatcher.listeners.stop_on_failure', function (IndexedServiceContainer $c) {
            return new StopOnFailureListener(
                $c->get('console.io')
            );
        }, ['event_dispatcher.listeners']);
        $container->define('event_dispatcher.listeners.rerun', function (IndexedServiceContainer $c) {
            return new RerunListener(
                $c->get('process.rerunner'),
                $c->get('process.prerequisites')
            );
        }, ['event_dispatcher.listeners']);
        $container->define('process.prerequisites', function (IndexedServiceContainer $c) {
            return new SuitePrerequisites(
                $c->get('process.executioncontext')
            );
        });
        $container->define('event_dispatcher.listeners.method_returned_null', function (IndexedServiceContainer $c) {
            return new MethodReturnedNullListener(
                $c->get('console.io'),
                $c->get('locator.resource_manager'),
                $c->get('code_generator'),
                $c->get('util.method_analyser')
            );
        }, ['event_dispatcher.listeners']);
        $container->define('util.method_analyser', function () {
            return new MethodAnalyser();
        });
        $container->define('util.reserved_words_checker', function () {
            return new ReservedWordsMethodNameChecker();
        });
        $container->define('util.class_name_checker', function () {
            return new ClassNameChecker();
        });
        $container->define('event_dispatcher.listeners.bootstrap', function (IndexedServiceContainer $c) {
            return new BootstrapListener(
                $c->get('console.io')
            );
        }, ['event_dispatcher.listeners']);
        $container->define('event_dispatcher.listeners.current_example_listener', function (IndexedServiceContainer $c) {
            return new CurrentExampleListener(
                $c->get('current_example')
            );
        }, ['event_dispatcher.listeners']);
    }

    
    private function setupGenerators(IndexedServiceContainer $container): void
    {
        $container->define('code_generator', function (IndexedServiceContainer $c) {
            $generator = new GeneratorManager();

            array_map(
                array($generator, 'registerGenerator'),
                $c->getByTag('code_generator.generators')
            );

            return $generator;
        });

        $container->define('code_generator.generators.specification', function (IndexedServiceContainer $c) {
            $io = $c->get('console.io');
            $specificationGenerator = new SpecificationGenerator(
                $io,
                $c->get('code_generator.templates'),
                $c->get('util.filesystem'),
                $c->get('process.executioncontext')
            );

            $classNameCheckGenerator = new ValidateClassNameSpecificationGenerator(
                $c->get('util.class_name_checker'),
                $io,
                $specificationGenerator
            );

            return new NewFileNotifyingGenerator(
                $classNameCheckGenerator,
                $c->get('event_dispatcher'),
                $c->get('util.filesystem')
            );
        }, ['code_generator.generators']);
        $container->define('code_generator.generators.class', function (IndexedServiceContainer $c) {
            $classGenerator = new ClassGenerator(
                $c->get('console.io'),
                $c->get('code_generator.templates'),
                $c->get('util.filesystem'),
                $c->get('process.executioncontext')
            );

            return new NewFileNotifyingGenerator(
                $classGenerator,
                $c->get('event_dispatcher'),
                $c->get('util.filesystem')
            );
        }, ['code_generator.generators']);
        $container->define('code_generator.generators.interface', function (IndexedServiceContainer $c) {
            $interfaceGenerator = new InterfaceGenerator(
                $c->get('console.io'),
                $c->get('code_generator.templates'),
                $c->get('util.filesystem'),
                $c->get('process.executioncontext')
            );

            return new NewFileNotifyingGenerator(
                $interfaceGenerator,
                $c->get('event_dispatcher'),
                $c->get('util.filesystem')
            );
        }, ['code_generator.generators']);
        $container->define('code_generator.writers.tokenized', function () {
            return new TokenizedCodeWriter(new ClassFileAnalyser());
        });
        $container->define('code_generator.generators.method', function (IndexedServiceContainer $c) {
            return new MethodGenerator(
                $c->get('console.io'),
                $c->get('code_generator.templates'),
                $c->get('util.filesystem'),
                $c->get('code_generator.writers.tokenized')
            );
        }, ['code_generator.generators']);
        $container->define('code_generator.generators.methodSignature', function (IndexedServiceContainer $c) {
            return new MethodSignatureGenerator(
                $c->get('console.io'),
                $c->get('code_generator.templates'),
                $c->get('util.filesystem')
            );
        }, ['code_generator.generators']);
        $container->define('code_generator.generators.returnConstant', function (IndexedServiceContainer $c) {
            return new ReturnConstantGenerator(
                $c->get('console.io'),
                $c->get('code_generator.templates'),
                $c->get('util.filesystem')
            );
        }, ['code_generator.generators']);

        $container->define('code_generator.generators.named_constructor', function (IndexedServiceContainer $c) {
            return new NamedConstructorGenerator(
                $c->get('console.io'),
                $c->get('code_generator.templates'),
                $c->get('util.filesystem'),
                $c->get('code_generator.writers.tokenized')
            );
        }, ['code_generator.generators']);

        $container->define('code_generator.generators.private_constructor', function (IndexedServiceContainer $c) {
            return new OneTimeGenerator(
                new ConfirmingGenerator(
                    $c->get('console.io'),
                    $c->getParam('generator.private-constructor.message'),
                    new PrivateConstructorGenerator(
                        $c->get('console.io'),
                        $c->get('code_generator.templates'),
                        $c->get('util.filesystem'),
                        $c->get('code_generator.writers.tokenized')
                    )
                )
            );
        }, ['code_generator.generators']);

        $container->define('code_generator.templates', function (IndexedServiceContainer $c) {
            $renderer = new TemplateRenderer(
                $c->get('util.filesystem')
            );
            $renderer->setLocations($c->getParam('code_generator.templates.paths', array()));

            return $renderer;
        });

        if (!empty($_SERVER['HOMEDRIVE']) && !empty($_SERVER['HOMEPATH'])) {
            $home = $_SERVER['HOMEDRIVE'].$_SERVER['HOMEPATH'];
        } else {
            $home = getenv('HOME');
        }

        $paths = [rtrim(getcwd(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '.phpspec'];

        if ($home) {
            $paths[] = rtrim($home, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '.phpspec';
        }

        $container->setParam('code_generator.templates.paths', $paths);
    }

    
    private function setupPresenter(IndexedServiceContainer $container): void
    {
        $presenterAssembler = new PresenterAssembler();
        $presenterAssembler->assemble($container);
    }

    
    private function setupLocator(IndexedServiceContainer $container): void
    {
        $container->define('locator.resource_manager', function (IndexedServiceContainer $c) {
            $manager = new PrioritizedResourceManager();

            array_map(
                array($manager, 'registerLocator'),
                $c->getByTag('locator.locators')
            );

            return $manager;
        });

        $container->addConfigurator(function (IndexedServiceContainer $c) {
            $suites = [];
            $arguments = $c->getParam('composer_suite_detection', false);
            if ($arguments !== false) {
                if ($arguments === true) {
                    $arguments = [];
                }
                $arguments = array_merge(array(
                    'root_directory' => '.',
                    'spec_prefix' => 'spec',
                ), (array) $arguments);
                $namespaceProvider = new ComposerPsrNamespaceProvider(
                    $arguments['root_directory'],
                    $arguments['spec_prefix']
                );
                foreach ($namespaceProvider->getNamespaces() as $namespace => $namespaceLocation) {
                    $psr4Prefix = null;
                    if ($namespaceLocation->getAutoloadingStandard() === NamespaceProvider::AUTOLOADING_STANDARD_PSR4) {
                        $psr4Prefix = $namespace;
                    }

                    $location = $namespaceLocation->getLocation();
                    if (!empty($location) && !is_dir($location)) {
                        mkdir($location, 0777, true);
                    }

                    $suites[str_replace('\\', '_', strtolower($namespace)).'suite'] =  [
                        'namespace' => $namespace,
                        'src_path' => $location,
                        'psr4_prefix' => $psr4Prefix,
                    ];
                }
            }

            $suites += $c->getParam('suites', array());

            if (count($suites) === 0) {
                $suites = array('main' => '');
            }
            foreach ($suites as $name => $suite) {
                $suite      = \is_array($suite) ? $suite : array('namespace' => $suite);
                $defaults = array(
                    'namespace'     => '',
                    'spec_prefix'   => 'spec',
                    'src_path'      => 'src',
                    'spec_path'     => '.',
                    'psr4_prefix'   => null
                );

                $config = array_merge($defaults, $suite);

                if (!empty($config['src_path']) && !is_dir($config['src_path'])) {
                    mkdir($config['src_path'], 0777, true);
                }
                if (!is_dir($config['spec_path'])) {
                    mkdir($config['spec_path'], 0777, true);
                }

                $c->define(
                    sprintf('locator.locators.%s_suite', $name),
                    function (IndexedServiceContainer $c) use ($config) {
                        return new PSR0Locator(
                            $c->get('util.filesystem'),
                            $config['namespace'],
                            $config['spec_prefix'],
                            $config['src_path'],
                            $config['spec_path'],
                            $config['psr4_prefix']
                        );
                    },
                    ['locator.locators']
                );
            }
        });
    }

    
    private function setupLoader(IndexedServiceContainer $container): void
    {
        $container->define('loader.resource_loader', function (IndexedServiceContainer $c) {
            return new ResourceLoader(
                $c->get('locator.resource_manager'),
                $c->get('util.method_analyser'),
                $c->get('event_dispatcher')
            );
        });

        $container->define('loader.resource_loader.spec_transformer.typehint_rewriter', function (IndexedServiceContainer $c) {
            return new TypeHintRewriter($c->get('analysis.typehintrewriter'));
        }, ['loader.resource_loader.spec_transformer']);

        $container->define('analysis.typehintrewriter', function ($c) {
            return new TokenizedTypeHintRewriter(
                $c->get('loader.transformer.typehintindex'),
                $c->get('analysis.namespaceresolver')
            );
        });
        $container->define('loader.transformer.typehintindex', function () {
            return new InMemoryTypeHintIndex();
        });
        $container->define('analysis.namespaceresolver.tokenized', function () {
            return new TokenizedNamespaceResolver();
        });
        $container->define('analysis.namespaceresolver', function ($c) {
            return new StaticRejectingNamespaceResolver($c->get('analysis.namespaceresolver.tokenized'));
        });
    }

    /**
     * @throws \RuntimeException
     */
    protected function setupFormatter(IndexedServiceContainer $container): void
    {
        $container->define(
            'formatter.formatters.progress',
            function (IndexedServiceContainer $c) {
                return new SpecFormatter\ProgressFormatter(
                    $c->get('formatter.presenter'),
                    $c->get('console.io'),
                    $c->get('event_dispatcher.listeners.stats')
                );
            }
        );
        $container->define(
            'formatter.formatters.pretty',
            function (IndexedServiceContainer $c) {
                return new SpecFormatter\PrettyFormatter(
                    $c->get('formatter.presenter'),
                    $c->get('console.io'),
                    $c->get('event_dispatcher.listeners.stats')
                );
            }
        );
        $container->define(
            'formatter.formatters.junit',
            function (IndexedServiceContainer $c) {
                return new SpecFormatter\JUnitFormatter(
                    $c->get('formatter.presenter'),
                    $c->get('console.io'),
                    $c->get('event_dispatcher.listeners.stats')
                );
            }
        );
        $container->define(
            'formatter.formatters.json',
            function (IndexedServiceContainer $c) {
                return new SpecFormatter\JsonFormatter(
                    $c->get('formatter.presenter'),
                    $c->get('console.io'),
                    $c->get('event_dispatcher.listeners.stats')
                );
            }
        );
        $container->define(
            'formatter.formatters.dot',
            function (IndexedServiceContainer $c) {
                return new SpecFormatter\DotFormatter(
                    $c->get('formatter.presenter'),
                    $c->get('console.io'),
                    $c->get('event_dispatcher.listeners.stats')
                );
            }
        );
        $container->define(
            'formatter.formatters.tap',
            function (IndexedServiceContainer $c) {
                return new SpecFormatter\TapFormatter(
                    $c->get('formatter.presenter'),
                    $c->get('console.io'),
                    $c->get('event_dispatcher.listeners.stats')
                );
            }
        );
        $container->define(
            'formatter.formatters.html',
            function (IndexedServiceContainer $c) {
                $io = new SpecFormatter\Html\HtmlIO();
                $template = new SpecFormatter\Html\Template($io);
                $factory = new SpecFormatter\Html\ReportItemFactory($template);
                $presenter = $c->get('formatter.presenter.html');

                return new SpecFormatter\HtmlFormatter(
                    $factory,
                    $presenter,
                    $io,
                    $c->get('event_dispatcher.listeners.stats')
                );
            }
        );
        $container->define(
            'formatter.formatters.h',
            function (IndexedServiceContainer $c) {
                return $c->get('formatter.formatters.html');
            }
        );

        $container->addConfigurator(function (IndexedServiceContainer $c) {
            $formatterName = $c->getParam('formatter.name', 'progress');

            $c->get('console.output')->setFormatter(new Formatter(
                $c->get('console.output')->isDecorated()
            ));

            try {
                $formatter = $c->get('formatter.formatters.'.$formatterName);
            } catch (\InvalidArgumentException) {
                throw new \RuntimeException(sprintf('Formatter not recognised: "%s"', $formatterName));
            }
            $c->set('event_dispatcher.listeners.formatter', $formatter, ['event_dispatcher.listeners']);
        });
    }

    
    private function setupRunner(IndexedServiceContainer $container): void
    {
        $container->define('runner.suite', function (IndexedServiceContainer $c) {
            return new SuiteRunner(
                $c->get('event_dispatcher'),
                $c->get('runner.specification')
            );
        });

        $container->define('runner.specification', function (IndexedServiceContainer $c) {
            return new SpecificationRunner(
                $c->get('event_dispatcher'),
                $c->get('runner.example')
            );
        });

        $container->define('runner.example', function (IndexedServiceContainer $c) {
            $runner = new ExampleRunner(
                $c->get('event_dispatcher'),
                $c->get('formatter.presenter')
            );

            array_map(
                array($runner, 'registerMaintainer'),
                $c->getByTag('runner.maintainers')
            );

            return $runner;
        });

        $container->define('runner.maintainers.errors', function (IndexedServiceContainer $c) {
            return new ErrorMaintainer(
                $c->getParam('runner.maintainers.errors.level', E_ALL ^ E_STRICT)
            );
        }, ['runner.maintainers']);
        $container->define('runner.maintainers.collaborators', function (IndexedServiceContainer $c) {
            return new CollaboratorsMaintainer(
                $c->get('unwrapper'),
                $c->get('loader.transformer.typehintindex')
            );
        }, ['runner.maintainers']);
        $container->define('runner.maintainers.let_letgo', function () {
            return new LetAndLetgoMaintainer();
        }, ['runner.maintainers']);

        $container->define('runner.maintainers.matchers', function (IndexedServiceContainer $c) {
            $matchers = $c->getByTag('matchers');
            return new MatchersMaintainer(
                $c->get('formatter.presenter'),
                $matchers
            );
        }, ['runner.maintainers']);

        $container->define('runner.maintainers.subject', function (IndexedServiceContainer $c) {
            return new SubjectMaintainer(
                $c->get('formatter.presenter'),
                $c->get('unwrapper'),
                $c->get('event_dispatcher'),
                $c->get('access_inspector')
            );
        }, ['runner.maintainers']);

        $container->define('unwrapper', function () {
            return new Unwrapper();
        });

        $container->define('access_inspector', function ($c) {
            return $c->get('access_inspector.magic');
        });

        $container->define('access_inspector.magic', function ($c) {
            return new MagicAwareAccessInspector($c->get('access_inspector.visibility'));
        });

        $container->define('access_inspector.visibility', function () {
            return new VisibilityAccessInspector();
        });
    }

    
    private function setupMatchers(IndexedServiceContainer $container): void
    {
        $container->define('matchers.identity', function (IndexedServiceContainer $c) {
            return new IdentityMatcher($c->get('formatter.presenter'));
        }, ['matchers']);
        $container->define('matchers.comparison', function (IndexedServiceContainer $c) {
            return new ComparisonMatcher($c->get('formatter.presenter'));
        }, ['matchers']);
        $container->define('matchers.throwm', function (IndexedServiceContainer $c) {
            return new ThrowMatcher($c->get('unwrapper'), $c->get('formatter.presenter'), new ReflectionFactory());
        }, ['matchers']);
        $container->define('matchers.trigger', function (IndexedServiceContainer $c) {
            return new TriggerMatcher($c->get('unwrapper'));
        }, ['matchers']);
        $container->define('matchers.type', function (IndexedServiceContainer $c) {
            return new TypeMatcher($c->get('formatter.presenter'));
        }, ['matchers']);
        $container->define('matchers.object_state', function (IndexedServiceContainer $c) {
            return new ObjectStateMatcher($c->get('formatter.presenter'));
        }, ['matchers']);
        $container->define('matchers.scalar', function (IndexedServiceContainer $c) {
            return new ScalarMatcher($c->get('formatter.presenter'));
        }, ['matchers']);
        $container->define('matchers.array_count', function (IndexedServiceContainer $c) {
            return new ArrayCountMatcher($c->get('formatter.presenter'));
        }, ['matchers']);
        $container->define('matchers.array_key', function (IndexedServiceContainer $c) {
            return new ArrayKeyMatcher($c->get('formatter.presenter'));
        }, ['matchers']);
        $container->define('matchers.array_key_with_value', function (IndexedServiceContainer $c) {
            return new ArrayKeyValueMatcher($c->get('formatter.presenter'));
        }, ['matchers']);
        $container->define('matchers.array_contain', function (IndexedServiceContainer $c) {
            return new ArrayContainMatcher($c->get('formatter.presenter'));
        }, ['matchers']);
        $container->define('matchers.string_start', function (IndexedServiceContainer $c) {
            return new StringStartMatcher($c->get('formatter.presenter'));
        }, ['matchers']);
        $container->define('matchers.string_end', function (IndexedServiceContainer $c) {
            return new StringEndMatcher($c->get('formatter.presenter'));
        }, ['matchers']);
        $container->define('matchers.string_regex', function (IndexedServiceContainer $c) {
            return new StringRegexMatcher($c->get('formatter.presenter'));
        }, ['matchers']);
        $container->define('matchers.string_contain', function (IndexedServiceContainer $c) {
            return new StringContainMatcher($c->get('formatter.presenter'));
        }, ['matchers']);
        $container->define('matchers.traversable_count', function (IndexedServiceContainer $c) {
            return new TraversableCountMatcher($c->get('formatter.presenter'));
        }, ['matchers']);
        $container->define('matchers.traversable_key', function (IndexedServiceContainer $c) {
            return new TraversableKeyMatcher($c->get('formatter.presenter'));
        }, ['matchers']);
        $container->define('matchers.traversable_key_with_value', function (IndexedServiceContainer $c) {
            return new TraversableKeyValueMatcher($c->get('formatter.presenter'));
        }, ['matchers']);
        $container->define('matchers.traversable_contain', function (IndexedServiceContainer $c) {
            return new TraversableContainMatcher($c->get('formatter.presenter'));
        }, ['matchers']);
        $container->define('matchers.iterate', function (IndexedServiceContainer $c) {
            return new IterateAsMatcher($c->get('formatter.presenter'));
        }, ['matchers']);
        $container->define('matchers.iterate_like', function (IndexedServiceContainer $c) {
            return new IterateLikeMatcher($c->get('formatter.presenter'));
        }, ['matchers']);
        $container->define('matchers.start_iterating', function (IndexedServiceContainer $c) {
            return new StartIteratingAsMatcher($c->get('formatter.presenter'));
        }, ['matchers']);
        $container->define('matchers.approximately', function (IndexedServiceContainer $c) {
            return new ApproximatelyMatcher($c->get('formatter.presenter'));
        }, ['matchers']);
    }

    
    private function setupRerunner(IndexedServiceContainer $container): void
    {
        $container->define('process.rerunner', function (IndexedServiceContainer $c) {
            return new OptionalReRunner(
                $c->get('process.rerunner.platformspecific'),
                $c->get('console.io')
            );
        });

        if ($container->has('process.rerunner.platformspecific')) {
            return;
        }

        $container->define('process.rerunner.platformspecific', function (IndexedServiceContainer $c) {
            return new CompositeReRunner(
                $c->getByTag('process.rerunner.platformspecific')
            );
        });
        $container->define('process.rerunner.platformspecific.pcntl', function (IndexedServiceContainer $c) {
            return PcntlReRunner::withExecutionContext(
                $c->get('process.phpexecutablefinder'),
                $c->get('process.executioncontext')
            );
        }, ['process.rerunner.platformspecific']);
        $container->define('process.rerunner.platformspecific.passthru', function (IndexedServiceContainer $c) {
            return ProcOpenReRunner::withExecutionContext(
                $c->get('process.phpexecutablefinder'),
                $c->get('process.executioncontext')
            );
        }, ['process.rerunner.platformspecific']);
        $container->define('process.rerunner.platformspecific.windowspassthru', function (IndexedServiceContainer $c) {
            return WindowsPassthruReRunner::withExecutionContext(
                $c->get('process.phpexecutablefinder'),
                $c->get('process.executioncontext')
            );
        }, ['process.rerunner.platformspecific']);
        $container->define('process.phpexecutablefinder', function () {
            return new PhpExecutableFinder();
        });
    }

    
    private function setupSubscribers(IndexedServiceContainer $container): void
    {
        $container->addConfigurator(function (IndexedServiceContainer $c): void {
            array_map(
                array($c->get('event_dispatcher'), 'addSubscriber'),
                $c->getByTag('event_dispatcher.listeners')
            );
        });
    }

    
    private function setupCurrentExample(IndexedServiceContainer $container): void
    {
        $container->define('current_example', function () {
            return new CurrentExampleTracker();
        });
    }

  
    private function setupShutdown(IndexedServiceContainer $container): void
    {
        $container->define('process.shutdown', function () {
            return new Shutdown();
        });
    }
}
