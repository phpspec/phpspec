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

namespace PhpSpec\Container;

use PhpSpec\CodeAnalysis\MagicAwareAccessInspector;
use PhpSpec\CodeAnalysis\StaticRejectingNamespaceResolver;
use PhpSpec\CodeAnalysis\TokenizedNamespaceResolver;
use PhpSpec\CodeAnalysis\TokenizedTypeHintRewriter;
use PhpSpec\CodeAnalysis\VisibilityAccessInspector;
use PhpSpec\Config\Manager as ConfigManger;
use PhpSpec\Console\Manager as ConsoleManager;
use PhpSpec\Console\Assembler\PresenterAssembler;
use PhpSpec\Console\Command;
use PhpSpec\Console\ConsoleIO;
use PhpSpec\Console\Prompter\Question;
use PhpSpec\Console\ResultConverter;
use PhpSpec\Factory\ReflectionFactory;
use PhpSpec\Process\Context\JsonExecutionContext;
use PhpSpec\Process\Prerequisites\SuitePrerequisites;
use PhpSpec\Process\Shutdown\UpdateConsoleAction;
use PhpSpec\Util\ClassFileAnalyser;
use PhpSpec\Util\Filesystem;
use PhpSpec\Util\ReservedWordsMethodNameChecker;
use PhpSpec\Process\ReRunner;
use PhpSpec\Util\MethodAnalyser;
use Symfony\Component\EventDispatcher\EventDispatcher;
use PhpSpec\CodeGenerator;
use PhpSpec\Formatter as SpecFormatter;
use PhpSpec\Listener;
use PhpSpec\Loader;
use PhpSpec\Locator;
use PhpSpec\Matcher;
use PhpSpec\Runner;
use PhpSpec\Wrapper;
use Symfony\Component\Process\PhpExecutableFinder;
use PhpSpec\Message\CurrentExampleTracker;
use PhpSpec\Process\Shutdown\Shutdown;
use Interop\Container\ContainerInterface;
use UltraLite\Container\Container;

class ServiceContainerConfigurer
{
    /**
     * @param Container $container
     */
    public function build(Container $container)
    {
        $this->setupConfigManager($container);
        $this->setupConsoleManager($container);
        $this->setupExecutionContext($container);
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
        $this->setupCurrentExample($container);
        $this->setupShutdown($container);
        $this->setupArrayServices($container);
    }

    private function setupConfigManager(Container $container)
    {
        $container->set('phpspec.config-manager', function (ContainerInterface $container) {
            return new ConfigManger();
        });
    }

    private function setupConsoleManager(Container $container)
    {
        $container->set('phpspec.console-manager', function (ContainerInterface $container) {
            return new ConsoleManager();
        });
    }

    private function setupExecutionContext(Container $container)
    {
        $container->set('process.executioncontext', function () {
            return JsonExecutionContext::fromEnv($_SERVER);
        });
    }

    private function setupIO(Container $container)
    {
        if (!$container->has('console.prompter')) {
            $container->set('console.prompter', function (ContainerInterface $container) {
                return new Question(
                    $container->get('phpspec.console-manager')
                );
            });
        }
        $container->set('console.io', function (ContainerInterface $container) {
            return new ConsoleIO(
                $container->get('phpspec.console-manager'),
                $container->get('phpspec.config-manager'),
                $container->get('console.prompter')
            );
        });
        $container->set('util.filesystem', function () {
            return new Filesystem();
        });
    }

    private function setupResultConverter(Container $container)
    {
        $container->set('console.result_converter', function () {
            return new ResultConverter();
        });
    }

    private function setupCommands(Container $container)
    {
        $container->set('console.commands.run', function (ContainerInterface $container) {
            $configManager = $container->get('phpspec.config-manager');
            $consoleManager = $container->get('phpspec.console-manager');
            $shutdown = $container->get('process.shutdown');
            $suiteRunner = $container->get('runner.suite');
            return new Command\RunCommand($configManager, $consoleManager, $shutdown, $suiteRunner);
        });

        $container->set('console.commands.describe', function (ContainerInterface $container) {
            $resourceManager = $container->get('locator.resource_manager');
            $codeGenerator = $container->get('code_generator');
            return new Command\DescribeCommand($resourceManager, $codeGenerator);
        });
    }

    private function setupConsoleEventDispatcher(Container $container)
    {
        $container->set('console_event_dispatcher', function (ContainerInterface $c) {
            $dispatcher = new EventDispatcher();

            array_map(
                array($dispatcher, 'addSubscriber'),
                $c->get('phpspec.console-event-listeners')
            );

            return $dispatcher;
        });
    }

    private function setupEventDispatcher(Container $container)
    {
        $container->set('event_dispatcher', function () {
            return new EventDispatcher();
        });

        $container->set('event_dispatcher.listeners.stats', function () {
            return new Listener\StatisticsCollector();
        });
        $container->set('event_dispatcher.listeners.class_not_found', function (ContainerInterface $c) {
            return new Listener\ClassNotFoundListener(
                $c->get('console.io'),
                $c->get('locator.resource_manager'),
                $c->get('code_generator')
            );
        });
        $container->set('event_dispatcher.listeners.collaborator_not_found', function (ContainerInterface $c) {
            return new Listener\CollaboratorNotFoundListener(
                $c->get('console.io'),
                $c->get('locator.resource_manager'),
                $c->get('code_generator')
            );
        });
        $container->set('event_dispatcher.listeners.collaborator_method_not_found', function (ContainerInterface $c) {
            return new Listener\CollaboratorMethodNotFoundListener(
                $c->get('console.io'),
                $c->get('locator.resource_manager'),
                $c->get('code_generator'),
                $c->get('util.reserved_words_checker')
            );
        });
        $container->set('event_dispatcher.listeners.named_constructor_not_found', function (ContainerInterface $c) {
            return new Listener\NamedConstructorNotFoundListener(
                $c->get('console.io'),
                $c->get('locator.resource_manager'),
                $c->get('code_generator')
            );
        });
        $container->set('event_dispatcher.listeners.method_not_found', function (ContainerInterface $c) {
            return new Listener\MethodNotFoundListener(
                $c->get('console.io'),
                $c->get('locator.resource_manager'),
                $c->get('code_generator'),
                $c->get('util.reserved_words_checker')
            );
        });
        $container->set('event_dispatcher.listeners.stop_on_failure', function (ContainerInterface $c) {
            return new Listener\StopOnFailureListener(
                $c->get('console.io')
            );
        });
        $container->set('event_dispatcher.listeners.rerun', function (ContainerInterface $c) {
            return new Listener\RerunListener(
                $c->get('process.rerunner'),
                $c->get('process.prerequisites')
            );
        });
        $container->set('process.prerequisites', function (ContainerInterface $c) {
            return new SuitePrerequisites(
                $c->get('process.executioncontext')
            );
        });
        $container->set('event_dispatcher.listeners.method_returned_null', function (ContainerInterface $c) {
            return new Listener\MethodReturnedNullListener(
                $c->get('console.io'),
                $c->get('locator.resource_manager'),
                $c->get('code_generator'),
                $c->get('util.method_analyser')
            );
        });
        $container->set('util.method_analyser', function () {
            return new MethodAnalyser();
        });
        $container->set('util.reserved_words_checker', function () {
            return new ReservedWordsMethodNameChecker();
        });
        $container->set('event_dispatcher.listeners.bootstrap', function (ContainerInterface $c) {
            return new Listener\BootstrapListener(
                $c->get('console.io')
            );
        });
        $container->set('event_dispatcher.listeners.current_example_listener', function (ContainerInterface $c) {
            return new Listener\CurrentExampleListener(
                $c->get('current_example')
            );
        });
    }

    private function setupGenerators(Container $container)
    {
        $container->set('code_generator', function (ContainerInterface $c) {
            $generator = new CodeGenerator\GeneratorManager();

            array_map(
                array($generator, 'registerGenerator'),
                $c->get('phpspec.code-generators')
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

        $container->set('code_generator.templates', function (ContainerInterface $c) {
            $renderer = new CodeGenerator\TemplateRenderer(
                $c->get('util.filesystem')
            );
            $templatePaths = $c->get('phpspec.config-manager')->optionsConfig()->getCodeGeneratorTemplatePaths();
            $renderer->setLocations($templatePaths);

            return $renderer;
        });
    }

    private function setupPresenter(Container $container)
    {
        $presenterAssembler = new PresenterAssembler();
        $presenterAssembler->assemble($container);
    }

    private function setupLocator(Container $container)
    {
        $container->set('locator.resource_manager', function (ContainerInterface $c) {
            $locatorFactory = $c->get('phpspec.locator-factory');
            $configManager = $c->get('phpspec.config-manager');
            return new Locator\PrioritizedResourceManager($locatorFactory, $configManager);
        });

        $container->set('phpspec.locator-factory', function (ContainerInterface $c) {
            $fileSystem = $c->get('util.filesystem');
            return new Locator\Factory($fileSystem);
        });
    }

    private function setupLoader(Container $container)
    {
        $container->set('loader.resource_loader', function (ContainerInterface $c) {
            return new Loader\ResourceLoader(
                $c->get('locator.resource_manager'),
                $c->get('util.method_analyser')
            );
        });
        if (PHP_VERSION >= 7) {
            $container->set('loader.resource_loader.spec_transformer.typehint_rewriter', function (ContainerInterface $c) {
                return new Loader\Transformer\TypeHintRewriter($c->get('analysis.typehintrewriter'));
            });
        }
        $container->set('analysis.typehintrewriter', function (ContainerInterface $container) {
            return new TokenizedTypeHintRewriter(
                $container->get('loader.transformer.typehintindex'),
                $container->get('analysis.namespaceresolver')
            );
        });
        $container->set('loader.transformer.typehintindex', function () {
            return new Loader\Transformer\InMemoryTypeHintIndex();
        });
        $container->set('analysis.namespaceresolver.tokenized', function () {
            return new TokenizedNamespaceResolver();
        });
        $container->set('analysis.namespaceresolver', function (ContainerInterface $container) {
            if (PHP_VERSION >= 7) {
                return new StaticRejectingNamespaceResolver($container->get('analysis.namespaceresolver.tokenized'));
            }
            return $container->get('analysis.namespaceresolver.tokenized');
        });
    }

    /**
     * @param Container $container
     *
     * @throws \RuntimeException
     */
    protected function setupFormatter(Container $container)
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
    }

    private function setupRunner(Container $container)
    {
        $container->set('runner.suite', function (ContainerInterface $c) {
            return new Runner\SuiteRunner(
                $c->get('event_dispatcher'),
                $c->get('runner.specification')
            );
        });

        $container->set('runner.specification', function (ContainerInterface $c) {
            return new Runner\SpecificationRunner(
                $c->get('event_dispatcher'),
                $c->get('runner.example')
            );
        });

        $container->set('runner.example', function (ContainerInterface $c) {
            $runner = new Runner\ExampleRunner(
                $c->get('event_dispatcher'),
                $c->get('formatter.presenter')
            );

            array_map(
                array($runner, 'registerMaintainer'),
                $c->get('phpspec.runner-maintainers')
            );

            return $runner;
        });

        $container->set('runner.maintainers.errors', function (ContainerInterface $c) {
            return new Runner\Maintainer\ErrorMaintainer(
                $c->get('phpspec.config-manager')->optionsConfig()->getErrorLevel()
            );
        });
        $container->set('runner.maintainers.collaborators', function (ContainerInterface $container) {
            return new Runner\Maintainer\CollaboratorsMaintainer(
                $container->get('unwrapper'),
                $container->get('loader.transformer.typehintindex')
            );
        });
        $container->set('runner.maintainers.let_letgo', function (ContainerInterface $container) {
            return new Runner\Maintainer\LetAndLetgoMaintainer();
        });

        $container->set('runner.maintainers.matchers', function (ContainerInterface $container) {
            $matchers = $container->get('phpspec.matchers');
            return new Runner\Maintainer\MatchersMaintainer(
                $container->get('formatter.presenter'),
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

        $container->set('unwrapper', function (ContainerInterface $container) {
            return new Wrapper\Unwrapper();
        });

        $container->set('access_inspector', function (ContainerInterface $container) {
            return $container->get('access_inspector.magic');
        });

        $container->set('access_inspector.magic', function (ContainerInterface $container) {
            return new MagicAwareAccessInspector($container->get('access_inspector.visibility'));
        });

        $container->set('access_inspector.visibility', function () {
            return new VisibilityAccessInspector();
        });
    }

    private function setupMatchers(Container $container)
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

    private function setupRerunner(Container $container)
    {
        $container->set('process.rerunner', function (ContainerInterface $c) {
            return new ReRunner\OptionalReRunner(
                $c->get('process.rerunner.platformspecific'),
                $c->get('console.io')
            );
        });

        if ($container->has('process.rerunner.platformspecific')) {
            return;
        }

        $container->set('process.rerunner.platformspecific', function (ContainerInterface $c) {
            return new ReRunner\CompositeReRunner(
                $c->get('phpspec.process.platform-specific-rerunners')
            );
        });
        $container->set('process.rerunner.platformspecific.pcntl', function (ContainerInterface $c) {
            return ReRunner\PcntlReRunner::withExecutionContext(
                $c->get('process.phpexecutablefinder'),
                $c->get('process.executioncontext')
            );
        });
        $container->set('process.rerunner.platformspecific.passthru', function (ContainerInterface $c) {
            return ReRunner\ProcOpenReRunner::withExecutionContext(
                $c->get('process.phpexecutablefinder'),
                $c->get('process.executioncontext')
            );
        });
        $container->set('process.rerunner.platformspecific.windowspassthru', function (ContainerInterface $c) {
            return ReRunner\WindowsPassthruReRunner::withExecutionContext(
                $c->get('process.phpexecutablefinder'),
                $c->get('process.executioncontext')
            );
        });
        $container->set('process.phpexecutablefinder', function () {
            return new PhpExecutableFinder();
        });
    }

    private function setupCurrentExample(Container $container)
    {
        $container->set('current_example', function () {
            return new CurrentExampleTracker();
        });
    }

    private function setupShutdown(Container $container)
    {
        $container->set('process.shutdown', function (ContainerInterface $container) {

            $shutdown = new Shutdown();
            
            $formatterName = $container->get('phpspec.config-manager')->optionsConfig()->getFormatterName();

            $currentFormatter = $container->get('formatter.formatters.'.$formatterName);
            
            $shutdown->registerAction(
                new UpdateConsoleAction(
                    $container->get('current_example'),
                    $currentFormatter
                )
            );
            return $shutdown;
        });
    }

    private function setupArrayServices(Container $container)
    {
        $container->set('phpspec.servicelist.console.commands', function (ContainerInterface $container) {
            return [
                'console.commands.run',
                'console.commands.describe',
            ];
        });

        $container->set('phpspec.console.commands', function (ContainerInterface $container) {
            return array_map(
                function ($serviceId) use ($container) {return $container->get($serviceId);},
                $container->get('phpspec.servicelist.console.commands')
            );
        });

        $container->set('phpspec.servicelist.spec-transformers', function (ContainerInterface $container) {
            if ($container->has('loader.resource_loader.spec_transformer.typehint_rewriter')) {
                return ['loader.resource_loader.spec_transformer.typehint_rewriter'];
            }
            return [];
        });

        $container->set('phpspec.spec-transformers', function (ContainerInterface $container) {
            return array_map(
                function ($serviceId) use ($container) {return $container->get($serviceId);},
                $container->get('phpspec.servicelist.spec-transformers')
            );
        });

        $container->set('phpspec.servicelist.formatter.differ-engines', function (ContainerInterface $container) {
            return [
                'formatter.presenter.differ.engines.string',
                'formatter.presenter.differ.engines.array',
                'formatter.presenter.differ.engines.object',
            ];
        });

        $container->set('phpspec.formatter.differ-engines', function (ContainerInterface $container) {
            return array_map(
                function ($serviceId) use ($container) {return $container->get($serviceId);},
                $container->get('phpspec.servicelist.formatter.differ-engines')
            );
        });

        $container->set('phpspec.servicelist.formatter.presenters', function (ContainerInterface $container) {
            return [
                'formatter.presenter.value.array_type_presenter',
                'formatter.presenter.value.boolean_type_presenter',
                'formatter.presenter.value.callable_type_presenter',
                'formatter.presenter.value.exception_type_presenter',
                'formatter.presenter.value.null_type_presenter',
                'formatter.presenter.value.object_type_presenter',
                'formatter.presenter.value.string_type_presenter',
            ];
        });

        $container->set('phpspec.formatter.presenters', function (ContainerInterface $container) {
            return array_map(
                function ($serviceId) use ($container) {return $container->get($serviceId);},
                $container->get('phpspec.servicelist.formatter.presenters')
            );
        });

        $container->set('phpspec.servicelist.event-listeners', function (ContainerInterface $container) {
            return [
                'event_dispatcher.listeners.bootstrap',
                'event_dispatcher.listeners.class_not_found',
                'event_dispatcher.listeners.collaborator_method_not_found',
                'event_dispatcher.listeners.collaborator_not_found',
                'event_dispatcher.listeners.current_example_listener',
                'event_dispatcher.listeners.method_not_found',
                'event_dispatcher.listeners.method_returned_null',
                'event_dispatcher.listeners.named_constructor_not_found',
                'event_dispatcher.listeners.rerun',
                'event_dispatcher.listeners.stats',
                'event_dispatcher.listeners.stop_on_failure',
            ];
        });

        $container->set('phpspec.event-listeners', function (ContainerInterface $container) {
            return array_map(
                function ($serviceId) use ($container) {return $container->get($serviceId);},
                $container->get('phpspec.servicelist.event-listeners')
            );
        });

        $container->set('phpspec.servicelist.console-event-listeners', function (ContainerInterface $container) {
            return [];
        });

        $container->set('phpspec.console-event-listeners', function (ContainerInterface $container) {
            return array_map(
                function ($serviceId) use ($container) {return $container->get($serviceId);},
                $container->get('phpspec.servicelist.console-event-listeners')
            );
        });

        $container->set('phpspec.servicelist.process.platform-specific-rerunners', function (ContainerInterface $container) {
            return [
                'process.rerunner.platformspecific.pcntl',
                'process.rerunner.platformspecific.passthru',
                'process.rerunner.platformspecific.windowspassthru',
            ];
        });

        $container->set('phpspec.process.platform-specific-rerunners', function (ContainerInterface $container) {
            return array_map(
                function ($serviceId) use ($container) {return $container->get($serviceId);},
                $container->get('phpspec.servicelist.process.platform-specific-rerunners')
            );
        });

        $container->set('phpspec.servicelist.code-generators', function (ContainerInterface $container) {
            return [
                'code_generator.generators.class',
                'code_generator.generators.interface',
                'code_generator.generators.method',
                'code_generator.generators.methodSignature',
                'code_generator.generators.named_constructor',
                'code_generator.generators.private_constructor',
                'code_generator.generators.returnConstant',
                'code_generator.generators.specification',
            ];
        });

        $container->set('phpspec.code-generators', function (ContainerInterface $container) {
            return array_map(
                function ($serviceId) use ($container) {return $container->get($serviceId);},
                $container->get('phpspec.servicelist.code-generators')
            );
        });

        $container->set('phpspec.servicelist.matchers', function (ContainerInterface $container) {
            return [
                'matchers.identity',
                'matchers.comparison',
                'matchers.throwm',
                'matchers.type',
                'matchers.object_state',
                'matchers.scalar',
                'matchers.array_count',
                'matchers.array_key',
                'matchers.array_key_with_value',
                'matchers.array_contain',
                'matchers.string_start',
                'matchers.string_end',
                'matchers.string_regex',
                'matchers.string_contain',
            ];
        });

        $container->set('phpspec.matchers', function (ContainerInterface $container) {
            return array_map(
                function ($serviceId) use ($container) {return $container->get($serviceId);},
                $container->get('phpspec.servicelist.matchers')
            );
        });

        $container->set('phpspec.servicelist.runner-maintainers', function (ContainerInterface $container) {
            return [
                'runner.maintainers.errors',
                'runner.maintainers.collaborators',
                'runner.maintainers.let_letgo',
                'runner.maintainers.matchers',
                'runner.maintainers.subject',
            ];
        });

        $container->set('phpspec.runner-maintainers', function (ContainerInterface $container) {
            return array_map(
                function ($serviceId) use ($container) {return $container->get($serviceId);},
                $container->get('phpspec.servicelist.runner-maintainers')
            );
        });
    }
}
