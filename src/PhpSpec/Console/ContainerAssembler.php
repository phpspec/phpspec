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

use PhpSpec\CodeAnalysis\MagicAwareAccessInspector;
use PhpSpec\CodeAnalysis\StaticRejectingNamespaceResolver;
use PhpSpec\CodeAnalysis\TokenizedNamespaceResolver;
use PhpSpec\CodeAnalysis\TokenizedTypeHintRewriter;
use PhpSpec\CodeAnalysis\VisibilityAccessInspector;
use PhpSpec\Console\Assembler\PresenterAssembler;
use PhpSpec\Console\Prompter\Question;
use PhpSpec\Factory\ReflectionFactory;
use PhpSpec\Process\Prerequisites\SuitePrerequisites;
use PhpSpec\Util\ClassFileAnalyser;
use PhpSpec\Util\Filesystem;
use PhpSpec\Util\ReservedWordsMethodNameChecker;
use PhpSpec\Process\ReRunner;
use PhpSpec\Util\MethodAnalyser;
use Symfony\Component\EventDispatcher\EventDispatcher;
use PhpSpec\Container\ServiceContainer;
use PhpSpec\CodeGenerator;
use PhpSpec\Formatter as SpecFormatter;
use PhpSpec\Listener;
use PhpSpec\Loader;
use PhpSpec\Locator;
use PhpSpec\Matcher;
use PhpSpec\Runner;
use PhpSpec\Wrapper;
use PhpSpec\Config\OptionsConfig;
use Symfony\Component\Process\PhpExecutableFinder;
use PhpSpec\Message\CurrentExampleTracker;
use PhpSpec\Process\Shutdown\Shutdown;
use Interop\Container\ContainerInterface;

class ContainerAssembler
{
    /**
     * @param ServiceContainer $container
     */
    public function build(ServiceContainer $container)
    {
        $this->setupConfigObject($container);
        $this->setupRegistry($container);
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

    private function setupConfigObject(ServiceContainer $container)
    {
        $configObject = $container;

        $container->setShared('phpspec.config-object', function (ContainerInterface $container) use ($configObject) {
            return $configObject;
        });
    }

    private function setupRegistry(ServiceContainer $container)
    {
        $registry = $container;

        $container->setShared('phpspec.registry', function (ContainerInterface $container) use ($registry) {
            return $registry;
        });
    }

    private function setupIO(ServiceContainer $container)
    {
        if (!$container->has('console.prompter')) {
            $container->setShared('console.prompter', function (ContainerInterface $container) {
                $registry = $container->get('phpspec.registry');
                return new Question(
                    $registry->get('console.input'),
                    $registry->get('console.output'),
                    $registry->get('console.helper_set')->get('question')
                );
            });
        }
        $container->setShared('console.io', function (ContainerInterface $container) {
            $configObject = $container->get('phpspec.config-object');
            $registry = $container->get('phpspec.registry');
            return new ConsoleIO(
                $registry->get('console.input'),
                $registry->get('console.output'),
                new OptionsConfig(
                    $configObject->getParam('stop_on_failure', false),
                    $configObject->getParam('code_generation', true),
                    $configObject->getParam('rerun', true),
                    $configObject->getParam('fake', false),
                    $configObject->getParam('bootstrap', false)
                ),
                $registry->get('console.prompter')
            );
        });
        $container->setShared('util.filesystem', function () {
            return new Filesystem();
        });
    }

    private function setupResultConverter(ServiceContainer $container)
    {
        $container->setShared('console.result_converter', function () {
            return new ResultConverter();
        });
    }

    private function setupCommands(ServiceContainer $container)
    {
        $container->setShared('console.commands.run', function () {
            return new Command\RunCommand();
        });

        $container->setShared('console.commands.describe', function () {
            return new Command\DescribeCommand();
        });
    }

    /**
     * @param ServiceContainer $container
     */
    private function setupConsoleEventDispatcher(ServiceContainer $container)
    {
        $container->setShared('console_event_dispatcher', function (ServiceContainer $c) {
            $dispatcher = new EventDispatcher();

            array_map(
                array($dispatcher, 'addSubscriber'),
                $c->getByPrefix('console_event_dispatcher.listeners')
            );

            return $dispatcher;
        });
    }

    /**
     * @param ServiceContainer $container
     */
    private function setupEventDispatcher(ServiceContainer $container)
    {
        $container->setShared('event_dispatcher', function () {
            return new EventDispatcher();
        });

        $container->setShared('event_dispatcher.listeners.stats', function () {
            return new Listener\StatisticsCollector();
        });
        $container->setShared('event_dispatcher.listeners.class_not_found', function (ContainerInterface $c) {
            return new Listener\ClassNotFoundListener(
                $c->get('console.io'),
                $c->get('locator.resource_manager'),
                $c->get('code_generator')
            );
        });
        $container->setShared('event_dispatcher.listeners.collaborator_not_found', function (ContainerInterface $c) {
            return new Listener\CollaboratorNotFoundListener(
                $c->get('console.io'),
                $c->get('locator.resource_manager'),
                $c->get('code_generator')
            );
        });
        $container->setShared('event_dispatcher.listeners.collaborator_method_not_found', function (ContainerInterface $c) {
            return new Listener\CollaboratorMethodNotFoundListener(
                $c->get('console.io'),
                $c->get('locator.resource_manager'),
                $c->get('code_generator'),
                $c->get('util.reserved_words_checker')
            );
        });
        $container->setShared('event_dispatcher.listeners.named_constructor_not_found', function (ContainerInterface $c) {
            return new Listener\NamedConstructorNotFoundListener(
                $c->get('console.io'),
                $c->get('locator.resource_manager'),
                $c->get('code_generator')
            );
        });
        $container->setShared('event_dispatcher.listeners.method_not_found', function (ContainerInterface $c) {
            return new Listener\MethodNotFoundListener(
                $c->get('console.io'),
                $c->get('locator.resource_manager'),
                $c->get('code_generator'),
                $c->get('util.reserved_words_checker')
            );
        });
        $container->setShared('event_dispatcher.listeners.stop_on_failure', function (ContainerInterface $c) {
            return new Listener\StopOnFailureListener(
                $c->get('console.io')
            );
        });
        $container->setShared('event_dispatcher.listeners.rerun', function (ContainerInterface $c) {
            return new Listener\RerunListener(
                $c->get('process.rerunner'),
                $c->get('process.prerequisites')
            );
        });
        $container->setShared('process.prerequisites', function (ContainerInterface $c) {
            return new SuitePrerequisites(
                $c->get('process.executioncontext')
            );
        });
        $container->setShared('event_dispatcher.listeners.method_returned_null', function (ContainerInterface $c) {
            return new Listener\MethodReturnedNullListener(
                $c->get('console.io'),
                $c->get('locator.resource_manager'),
                $c->get('code_generator'),
                $c->get('util.method_analyser')
            );
        });
        $container->setShared('util.method_analyser', function () {
            return new MethodAnalyser();
        });
        $container->setShared('util.reserved_words_checker', function () {
            return new ReservedWordsMethodNameChecker();
        });
        $container->setShared('event_dispatcher.listeners.bootstrap', function (ContainerInterface $c) {
            return new Listener\BootstrapListener(
                $c->get('console.io')
            );
        });
        $container->setShared('event_dispatcher.listeners.current_example_listener', function (ContainerInterface $c) {
            return new Listener\CurrentExampleListener(
                $c->get('current_example')
            );
        });
    }

    /**
     * @param ServiceContainer $container
     */
    private function setupGenerators(ServiceContainer $container)
    {
        $container->setShared('code_generator', function (ServiceContainer $c) {
            $generator = new CodeGenerator\GeneratorManager();

            array_map(
                array($generator, 'registerGenerator'),
                $c->getByPrefix('code_generator.generators')
            );

            return $generator;
        });

        $container->set('code_generator.generators.specification', function (ContainerInterface $c) {
            $specificationGenerator =  new CodeGenerator\Generator\SpecificationGenerator(
                $c->get('console.io'),
                $c->get('code_generator.templates'),
                $c->get('util.filesystem'),
                $c->get('process.executioncontext')
            );

            return new CodeGenerator\Generator\NewFileNotifyingGenerator(
                $specificationGenerator,
                $c->get('event_dispatcher'),
                $c->get('util.filesystem')
            );
        });
        $container->set('code_generator.generators.class', function (ContainerInterface $c) {
            $classGenerator = new CodeGenerator\Generator\ClassGenerator(
                $c->get('console.io'),
                $c->get('code_generator.templates'),
                $c->get('util.filesystem'),
                $c->get('process.executioncontext')
            );

            return new CodeGenerator\Generator\NewFileNotifyingGenerator(
                $classGenerator,
                $c->get('event_dispatcher'),
                $c->get('util.filesystem')
            );
        });
        $container->set('code_generator.generators.interface', function (ContainerInterface $c) {
            $interfaceGenerator = new CodeGenerator\Generator\InterfaceGenerator(
                $c->get('console.io'),
                $c->get('code_generator.templates'),
                $c->get('util.filesystem'),
                $c->get('process.executioncontext')
            );

            return new CodeGenerator\Generator\NewFileNotifyingGenerator(
                $interfaceGenerator,
                $c->get('event_dispatcher'),
                $c->get('util.filesystem')
            );
        });
        $container->set('code_generator.writers.tokenized', function () {
            return new CodeGenerator\Writer\TokenizedCodeWriter(new ClassFileAnalyser());
        });
        $container->set('code_generator.generators.method', function (ContainerInterface $c) {
            return new CodeGenerator\Generator\MethodGenerator(
                $c->get('console.io'),
                $c->get('code_generator.templates'),
                $c->get('util.filesystem'),
                $c->get('code_generator.writers.tokenized')
            );
        });
        $container->set('code_generator.generators.methodSignature', function (ContainerInterface $c) {
            return new CodeGenerator\Generator\MethodSignatureGenerator(
                $c->get('console.io'),
                $c->get('code_generator.templates'),
                $c->get('util.filesystem')
            );
        });
        $container->set('code_generator.generators.returnConstant', function (ContainerInterface $c) {
            return new CodeGenerator\Generator\ReturnConstantGenerator(
                $c->get('console.io'),
                $c->get('code_generator.templates'),
                $c->get('util.filesystem')
            );
        });

        $container->set('code_generator.generators.named_constructor', function (ContainerInterface $c) {
            return new CodeGenerator\Generator\NamedConstructorGenerator(
                $c->get('console.io'),
                $c->get('code_generator.templates'),
                $c->get('util.filesystem'),
                $c->get('code_generator.writers.tokenized')
            );
        });

        $container->set('code_generator.generators.private_constructor', function (ContainerInterface $c) {
            return new CodeGenerator\Generator\PrivateConstructorGenerator(
                $c->get('console.io'),
                $c->get('code_generator.templates'),
                $c->get('util.filesystem'),
                $c->get('code_generator.writers.tokenized')
            );
        });

        $container->setShared('code_generator.templates', function (ContainerInterface $c) {
            $configObject = $c->get('phpspec.config-object');
            $renderer = new CodeGenerator\TemplateRenderer(
                $c->get('util.filesystem')
            );
            $renderer->setLocations($configObject->getParam('code_generator.templates.paths', array()));

            return $renderer;
        });

        if (!empty($_SERVER['HOMEDRIVE']) && !empty($_SERVER['HOMEPATH'])) {
            $home = $_SERVER['HOMEDRIVE'].$_SERVER['HOMEPATH'];
        } else {
            $home = getenv('HOME');
        }

        $container->setParam('code_generator.templates.paths', array(
            rtrim(getcwd(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'.phpspec',
            rtrim($home, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'.phpspec',
        ));
    }

    /**
     * @param ServiceContainer $container
     */
    private function setupPresenter(ServiceContainer $container)
    {
        $presenterAssembler = new PresenterAssembler();
        $presenterAssembler->assemble($container);
    }

    /**
     * @param ServiceContainer $container
     */
    private function setupLocator(ServiceContainer $container)
    {
        $container->setShared('locator.resource_manager', function (ContainerInterface $c) {
            $configObject = $c->get('phpspec.config-object');
            $manager = new Locator\PrioritizedResourceManager();

            array_map(
                array($manager, 'registerLocator'),
                $configObject->getByPrefix('locator.locators')
            );

            return $manager;
        });

        $container->addConfigurator(function (ServiceContainer $c) {
            $suites = $c->getParam('suites', array('main' => ''));

            foreach ($suites as $name => $suite) {
                $suite      = is_array($suite) ? $suite : array('namespace' => $suite);
                $defaults = array(
                    'namespace'     => '',
                    'spec_prefix'   => 'spec',
                    'src_path'      => 'src',
                    'spec_path'     => '.',
                    'psr4_prefix'   => null
                );

                $config = array_merge($defaults, $suite);

                if (!is_dir($config['src_path'])) {
                    mkdir($config['src_path'], 0777, true);
                }
                if (!is_dir($config['spec_path'])) {
                    mkdir($config['spec_path'], 0777, true);
                }

                $c->set(
                    sprintf('locator.locators.%s_suite', $name),
                    function (ServiceContainer $c) use ($config) {
                        return new Locator\PSR0\PSR0Locator(
                            $c->get('util.filesystem'),
                            $config['namespace'],
                            $config['spec_prefix'],
                            $config['src_path'],
                            $config['spec_path'],
                            $config['psr4_prefix']
                        );
                    }
                );
            }
        });
    }

    /**
     * @param ServiceContainer $container
     */
    private function setupLoader(ServiceContainer $container)
    {
        $container->setShared('loader.resource_loader', function (ContainerInterface $c) {
            return new Loader\ResourceLoader(
                $c->get('locator.resource_manager'),
                $c->get('util.method_analyser')
            );
        });
        if (PHP_VERSION >= 7) {
            $container->setShared('loader.resource_loader.spec_transformer.typehint_rewriter', function (ContainerInterface $c) {
                return new Loader\Transformer\TypeHintRewriter($c->get('analysis.typehintrewriter'));
            });
        }
        $container->setShared('analysis.typehintrewriter', function (ContainerInterface $c) {
            return new TokenizedTypeHintRewriter(
                $c->get('loader.transformer.typehintindex'),
                $c->get('analysis.namespaceresolver')
            );
        });
        $container->setShared('loader.transformer.typehintindex', function() {
            return new Loader\Transformer\InMemoryTypeHintIndex();
        });
        $container->setShared('analysis.namespaceresolver.tokenized', function() {
            return new TokenizedNamespaceResolver();
        });
        $container->setShared('analysis.namespaceresolver', function (ContainerInterface $c) {
            if (PHP_VERSION >= 7) {
                return new StaticRejectingNamespaceResolver($c->get('analysis.namespaceresolver.tokenized'));
            }
            return $c->get('analysis.namespaceresolver.tokenized');
        });
    }

    /**
     * @param ServiceContainer $container
     *
     * @throws \RuntimeException
     */
    protected function setupFormatter(ServiceContainer $container)
    {
        $container->set(
            'formatter.formatters.progress',
            function (ContainerInterface $c) {
                return new SpecFormatter\ProgressFormatter(
                    $c->get('formatter.presenter'),
                    $c->get('console.io'),
                    $c->get('event_dispatcher.listeners.stats')
                );
            }
        );
        $container->set(
            'formatter.formatters.pretty',
            function (ContainerInterface $c) {
                return new SpecFormatter\PrettyFormatter(
                    $c->get('formatter.presenter'),
                    $c->get('console.io'),
                    $c->get('event_dispatcher.listeners.stats')
                );
            }
        );
        $container->set(
            'formatter.formatters.junit',
            function (ContainerInterface $c) {
                return new SpecFormatter\JUnitFormatter(
                    $c->get('formatter.presenter'),
                    $c->get('console.io'),
                    $c->get('event_dispatcher.listeners.stats')
                );
            }
        );
        $container->set(
            'formatter.formatters.dot',
            function (ContainerInterface $c) {
                return new SpecFormatter\DotFormatter(
                    $c->get('formatter.presenter'),
                    $c->get('console.io'),
                    $c->get('event_dispatcher.listeners.stats')
                );
            }
        );
        $container->set(
            'formatter.formatters.tap',
            function (ContainerInterface $c) {
                return new SpecFormatter\TapFormatter(
                    $c->get('formatter.presenter'),
                    $c->get('console.io'),
                    $c->get('event_dispatcher.listeners.stats')
                );
            }
        );
        $container->set(
            'formatter.formatters.html',
            function (ContainerInterface $c) {
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
        $container->set(
            'formatter.formatters.h',
            function (ContainerInterface $c) {
                return $c->get('formatter.formatters.html');
            }
        );

        $container->addConfigurator(function (ServiceContainer $c) {
            $formatterName = $c->getParam('formatter.name', 'progress');

            $c->get('console.output')->setFormatter(new Formatter(
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
    private function setupRunner(ServiceContainer $container)
    {
        $container->setShared('runner.suite', function (ContainerInterface $c) {
            return new Runner\SuiteRunner(
                $c->get('event_dispatcher'),
                $c->get('runner.specification')
            );
        });

        $container->setShared('runner.specification', function (ContainerInterface $c) {
            return new Runner\SpecificationRunner(
                $c->get('event_dispatcher'),
                $c->get('runner.example')
            );
        });

        $container->setShared('runner.example', function (ContainerInterface $c) {
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

        $container->set('runner.maintainers.errors', function (ContainerInterface $c) {
            $configObject = $c->get('phpspec.config-object');
            return new Runner\Maintainer\ErrorMaintainer(
                $configObject->getParam('runner.maintainers.errors.level', E_ALL ^ E_STRICT)
            );
        });
        $container->set('runner.maintainers.collaborators', function (ContainerInterface $c) {
            return new Runner\Maintainer\CollaboratorsMaintainer(
                $c->get('unwrapper'),
                $c->get('loader.transformer.typehintindex')
            );
        });
        $container->set('runner.maintainers.let_letgo', function () {
            return new Runner\Maintainer\LetAndLetgoMaintainer();
        });

        $container->set('runner.maintainers.matchers', function (ServiceContainer $c) {
            $matchers = $c->getByPrefix('matchers');
            return new Runner\Maintainer\MatchersMaintainer(
                $c->get('formatter.presenter'),
                $matchers
            );
        });

        $container->set('runner.maintainers.subject', function (ContainerInterface $c) {
            return new Runner\Maintainer\SubjectMaintainer(
                $c->get('formatter.presenter'),
                $c->get('unwrapper'),
                $c->get('event_dispatcher'),
                $c->get('access_inspector')
            );
        });

        $container->setShared('unwrapper', function () {
            return new Wrapper\Unwrapper();
        });

        $container->setShared('access_inspector', function($c) {
            return $c->get('access_inspector.magic');
        });

        $container->setShared('access_inspector.magic', function(ContainerInterface $c) {
            return new MagicAwareAccessInspector($c->get('access_inspector.visibility'));
        });

        $container->setShared('access_inspector.visibility', function() {
            return new VisibilityAccessInspector();
        });
    }

    /**
     * @param ServiceContainer $container
     */
    private function setupMatchers(ServiceContainer $container)
    {
        $container->set('matchers.identity', function (ContainerInterface $c) {
            return new Matcher\IdentityMatcher($c->get('formatter.presenter'));
        });
        $container->set('matchers.comparison', function (ContainerInterface $c) {
            return new Matcher\ComparisonMatcher($c->get('formatter.presenter'));
        });
        $container->set('matchers.throwm', function (ContainerInterface $c) {
            return new Matcher\ThrowMatcher($c->get('unwrapper'), $c->get('formatter.presenter'), new ReflectionFactory());
        });
        $container->set('matchers.type', function (ContainerInterface $c) {
            return new Matcher\TypeMatcher($c->get('formatter.presenter'));
        });
        $container->set('matchers.object_state', function (ContainerInterface $c) {
            return new Matcher\ObjectStateMatcher($c->get('formatter.presenter'));
        });
        $container->set('matchers.scalar', function (ContainerInterface $c) {
            return new Matcher\ScalarMatcher($c->get('formatter.presenter'));
        });
        $container->set('matchers.array_count', function (ContainerInterface $c) {
            return new Matcher\ArrayCountMatcher($c->get('formatter.presenter'));
        });
        $container->set('matchers.array_key', function (ContainerInterface $c) {
            return new Matcher\ArrayKeyMatcher($c->get('formatter.presenter'));
        });
        $container->set('matchers.array_key_with_value', function (ContainerInterface $c) {
            return new Matcher\ArrayKeyValueMatcher($c->get('formatter.presenter'));
        });
        $container->set('matchers.array_contain', function (ContainerInterface $c) {
            return new Matcher\ArrayContainMatcher($c->get('formatter.presenter'));
        });
        $container->set('matchers.string_start', function (ContainerInterface $c) {
            return new Matcher\StringStartMatcher($c->get('formatter.presenter'));
        });
        $container->set('matchers.string_end', function (ContainerInterface $c) {
            return new Matcher\StringEndMatcher($c->get('formatter.presenter'));
        });
        $container->set('matchers.string_regex', function (ContainerInterface $c) {
            return new Matcher\StringRegexMatcher($c->get('formatter.presenter'));
        });
        $container->set('matchers.string_contain', function (ContainerInterface $c) {
            return new Matcher\StringContainMatcher($c->get('formatter.presenter'));
        });
    }

    /**
     * @param ServiceContainer $container
     */
    private function setupRerunner(ServiceContainer $container)
    {
        $container->setShared('process.rerunner', function (ContainerInterface $c) {
            return new ReRunner\OptionalReRunner(
                $c->get('process.rerunner.platformspecific'),
                $c->get('console.io')
            );
        });

        if ($container->has('process.rerunner.platformspecific')) {
            return;
        }

        $container->setShared('process.rerunner.platformspecific', function (ServiceContainer $c) {
            return new ReRunner\CompositeReRunner(
                $c->getByPrefix('process.rerunner.platformspecific')
            );
        });
        $container->setShared('process.rerunner.platformspecific.pcntl', function (ContainerInterface $c) {
            return ReRunner\PcntlReRunner::withExecutionContext(
                $c->get('process.phpexecutablefinder'),
                $c->get('process.executioncontext')
            );
        });
        $container->setShared('process.rerunner.platformspecific.passthru', function (ContainerInterface $c) {
            return ReRunner\ProcOpenReRunner::withExecutionContext(
                $c->get('process.phpexecutablefinder'),
                $c->get('process.executioncontext')
            );
        });
        $container->setShared('process.rerunner.platformspecific.windowspassthru', function (ContainerInterface $c) {
            return ReRunner\WindowsPassthruReRunner::withExecutionContext(
                $c->get('process.phpexecutablefinder'),
                $c->get('process.executioncontext')
            );
        });
        $container->setShared('process.phpexecutablefinder', function () {
            return new PhpExecutableFinder();
        });
    }

    /**
     * @param ServiceContainer $container
     */
    private function setupSubscribers(ServiceContainer $container)
    {
        $container->addConfigurator(function (ServiceContainer $c) {
            array_map(
                array($c->get('event_dispatcher'), 'addSubscriber'),
                $c->getByPrefix('event_dispatcher.listeners')
            );
        });
    }

    /**
     * @param ServiceContainer $container
     */
    private function setupCurrentExample(ServiceContainer $container)
    {
        $container->setShared('current_example', function () {
            return new CurrentExampleTracker();
        });
    }

  /**
   * @param ServiceContainer $container
   */
    private function setupShutdown(ServiceContainer $container)
    {
        $container->setShared('process.shutdown', function() {
            return new Shutdown();
        });
    }
}
