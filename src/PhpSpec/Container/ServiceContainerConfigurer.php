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

class ServiceContainerConfigurer
{
    /**
     * @param ServiceContainer $container
     */
    public function build(ServiceContainer $container)
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

    private function setupConfigManager(ServiceContainer $container)
    {
        $container->setShared('phpspec.config-manager', function (ServiceContainer $container) {
            return new ConfigManger();
        });
    }

    private function setupConsoleManager(ServiceContainer $container)
    {
        $container->setShared('phpspec.console-manager', function (ServiceContainer $container) {
            return new ConsoleManager();
        });
    }

    private function setupExecutionContext(ServiceContainer $container)
    {
        $container->setShared('process.executioncontext', function () {
            return JsonExecutionContext::fromEnv($_SERVER);
        });
    }

    private function setupIO(ServiceContainer $container)
    {
        if (!$container->has('console.prompter')) {
            $container->setShared('console.prompter', function ($c) {
                return new Question(
                    $c->get('phpspec.console-manager')
                );
            });
        }
        $container->setShared('console.io', function (ServiceContainer $c) {
            return new ConsoleIO(
                $c->get('phpspec.console-manager'),
                $c->get('phpspec.config-manager'),
                $c->get('console.prompter')
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
                $c->get('phpspec.console-event-listeners')
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
        $container->setShared('event_dispatcher.listeners.class_not_found', function (ServiceContainer $c) {
            return new Listener\ClassNotFoundListener(
                $c->get('console.io'),
                $c->get('locator.resource_manager'),
                $c->get('code_generator')
            );
        });
        $container->setShared('event_dispatcher.listeners.collaborator_not_found', function (ServiceContainer $c) {
            return new Listener\CollaboratorNotFoundListener(
                $c->get('console.io'),
                $c->get('locator.resource_manager'),
                $c->get('code_generator')
            );
        });
        $container->setShared('event_dispatcher.listeners.collaborator_method_not_found', function (ServiceContainer $c) {
            return new Listener\CollaboratorMethodNotFoundListener(
                $c->get('console.io'),
                $c->get('locator.resource_manager'),
                $c->get('code_generator'),
                $c->get('util.reserved_words_checker')
            );
        });
        $container->setShared('event_dispatcher.listeners.named_constructor_not_found', function (ServiceContainer $c) {
            return new Listener\NamedConstructorNotFoundListener(
                $c->get('console.io'),
                $c->get('locator.resource_manager'),
                $c->get('code_generator')
            );
        });
        $container->setShared('event_dispatcher.listeners.method_not_found', function (ServiceContainer $c) {
            return new Listener\MethodNotFoundListener(
                $c->get('console.io'),
                $c->get('locator.resource_manager'),
                $c->get('code_generator'),
                $c->get('util.reserved_words_checker')
            );
        });
        $container->setShared('event_dispatcher.listeners.stop_on_failure', function (ServiceContainer $c) {
            return new Listener\StopOnFailureListener(
                $c->get('console.io')
            );
        });
        $container->setShared('event_dispatcher.listeners.rerun', function (ServiceContainer $c) {
            return new Listener\RerunListener(
                $c->get('process.rerunner'),
                $c->get('process.prerequisites')
            );
        });
        $container->setShared('process.prerequisites', function (ServiceContainer $c) {
            return new SuitePrerequisites(
                $c->get('process.executioncontext')
            );
        });
        $container->setShared('event_dispatcher.listeners.method_returned_null', function (ServiceContainer $c) {
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
        $container->setShared('event_dispatcher.listeners.bootstrap', function (ServiceContainer $c) {
            return new Listener\BootstrapListener(
                $c->get('console.io')
            );
        });
        $container->setShared('event_dispatcher.listeners.current_example_listener', function (ServiceContainer $c) {
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
                $c->get('phpspec.code-generators')
            );

            return $generator;
        });

        $container->set('code_generator.generators.specification', function (ServiceContainer $c) {
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
        $container->set('code_generator.generators.class', function (ServiceContainer $c) {
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
        $container->set('code_generator.generators.interface', function (ServiceContainer $c) {
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
        $container->set('code_generator.generators.method', function (ServiceContainer $c) {
            return new CodeGenerator\Generator\MethodGenerator(
                $c->get('console.io'),
                $c->get('code_generator.templates'),
                $c->get('util.filesystem'),
                $c->get('code_generator.writers.tokenized')
            );
        });
        $container->set('code_generator.generators.methodSignature', function (ServiceContainer $c) {
            return new CodeGenerator\Generator\MethodSignatureGenerator(
                $c->get('console.io'),
                $c->get('code_generator.templates'),
                $c->get('util.filesystem')
            );
        });
        $container->set('code_generator.generators.returnConstant', function (ServiceContainer $c) {
            return new CodeGenerator\Generator\ReturnConstantGenerator(
                $c->get('console.io'),
                $c->get('code_generator.templates'),
                $c->get('util.filesystem')
            );
        });

        $container->set('code_generator.generators.named_constructor', function (ServiceContainer $c) {
            return new CodeGenerator\Generator\NamedConstructorGenerator(
                $c->get('console.io'),
                $c->get('code_generator.templates'),
                $c->get('util.filesystem'),
                $c->get('code_generator.writers.tokenized')
            );
        });

        $container->set('code_generator.generators.private_constructor', function (ServiceContainer $c) {
            return new CodeGenerator\Generator\PrivateConstructorGenerator(
                $c->get('console.io'),
                $c->get('code_generator.templates'),
                $c->get('util.filesystem'),
                $c->get('code_generator.writers.tokenized')
            );
        });

        $container->setShared('code_generator.templates', function (ServiceContainer $c) {
            $renderer = new CodeGenerator\TemplateRenderer(
                $c->get('util.filesystem')
            );
            $templatePaths = $c->get('phpspec.config-manager')->optionsConfig()->getCodeGeneratorTemplatePaths();
            $renderer->setLocations($templatePaths);

            return $renderer;
        });
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
        $container->setShared('locator.resource_manager', function (ServiceContainer $c) {
            $locatorFactory = $c->get('phpspec.locator-factory');
            $configManager = $c->get('phpspec.config-manager');
            return new Locator\PrioritizedResourceManager($locatorFactory, $configManager);
        });

        $container->setShared('phpspec.locator-factory', function (ServiceContainer $c) {
            $fileSystem = $c->get('util.filesystem');
            return new Locator\Factory($fileSystem);
        });
    }

    /**
     * @param ServiceContainer $container
     */
    private function setupLoader(ServiceContainer $container)
    {
        $container->setShared('loader.resource_loader', function (ServiceContainer $c) {
            return new Loader\ResourceLoader(
                $c->get('locator.resource_manager'),
                $c->get('util.method_analyser')
            );
        });
        if (PHP_VERSION >= 7) {
            $container->setShared('loader.resource_loader.spec_transformer.typehint_rewriter', function (ServiceContainer $c) {
                return new Loader\Transformer\TypeHintRewriter($c->get('analysis.typehintrewriter'));
            });
        }
        $container->setShared('analysis.typehintrewriter', function ($c) {
            return new TokenizedTypeHintRewriter(
                $c->get('loader.transformer.typehintindex'),
                $c->get('analysis.namespaceresolver')
            );
        });
        $container->setShared('loader.transformer.typehintindex', function () {
            return new Loader\Transformer\InMemoryTypeHintIndex();
        });
        $container->setShared('analysis.namespaceresolver.tokenized', function () {
            return new TokenizedNamespaceResolver();
        });
        $container->setShared('analysis.namespaceresolver', function ($c) {
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
            function (ServiceContainer $c) {
                return new SpecFormatter\ProgressFormatter(
                    $c->get('formatter.presenter'),
                    $c->get('console.io'),
                    $c->get('event_dispatcher.listeners.stats')
                );
            }
        );
        $container->set(
            'formatter.formatters.pretty',
            function (ServiceContainer $c) {
                return new SpecFormatter\PrettyFormatter(
                    $c->get('formatter.presenter'),
                    $c->get('console.io'),
                    $c->get('event_dispatcher.listeners.stats')
                );
            }
        );
        $container->set(
            'formatter.formatters.junit',
            function (ServiceContainer $c) {
                return new SpecFormatter\JUnitFormatter(
                    $c->get('formatter.presenter'),
                    $c->get('console.io'),
                    $c->get('event_dispatcher.listeners.stats')
                );
            }
        );
        $container->set(
            'formatter.formatters.dot',
            function (ServiceContainer $c) {
                return new SpecFormatter\DotFormatter(
                    $c->get('formatter.presenter'),
                    $c->get('console.io'),
                    $c->get('event_dispatcher.listeners.stats')
                );
            }
        );
        $container->set(
            'formatter.formatters.tap',
            function (ServiceContainer $c) {
                return new SpecFormatter\TapFormatter(
                    $c->get('formatter.presenter'),
                    $c->get('console.io'),
                    $c->get('event_dispatcher.listeners.stats')
                );
            }
        );
        $container->set(
            'formatter.formatters.html',
            function (ServiceContainer $c) {
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
            function (ServiceContainer $c) {
                return $c->get('formatter.formatters.html');
            }
        );
    }

    /**
     * @param ServiceContainer $container
     */
    private function setupRunner(ServiceContainer $container)
    {
        $container->setShared('runner.suite', function (ServiceContainer $c) {
            return new Runner\SuiteRunner(
                $c->get('event_dispatcher'),
                $c->get('runner.specification')
            );
        });

        $container->setShared('runner.specification', function (ServiceContainer $c) {
            return new Runner\SpecificationRunner(
                $c->get('event_dispatcher'),
                $c->get('runner.example')
            );
        });

        $container->setShared('runner.example', function (ServiceContainer $c) {
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

        $container->set('runner.maintainers.errors', function (ServiceContainer $c) {
            return new Runner\Maintainer\ErrorMaintainer(
                $c->get('phpspec.config-manager')->optionsConfig()->getErrorLevel()
            );
        });
        $container->set('runner.maintainers.collaborators', function (ServiceContainer $c) {
            return new Runner\Maintainer\CollaboratorsMaintainer(
                $c->get('unwrapper'),
                $c->get('loader.transformer.typehintindex')
            );
        });
        $container->set('runner.maintainers.let_letgo', function () {
            return new Runner\Maintainer\LetAndLetgoMaintainer();
        });

        $container->set('runner.maintainers.matchers', function (ServiceContainer $c) {
            $matchers = $c->get('phpspec.matchers');
            return new Runner\Maintainer\MatchersMaintainer(
                $c->get('formatter.presenter'),
                $matchers
            );
        });

        $container->set('runner.maintainers.subject', function (ServiceContainer $c) {
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

        $container->setShared('access_inspector', function ($c) {
            return $c->get('access_inspector.magic');
        });

        $container->setShared('access_inspector.magic', function ($c) {
            return new MagicAwareAccessInspector($c->get('access_inspector.visibility'));
        });

        $container->setShared('access_inspector.visibility', function () {
            return new VisibilityAccessInspector();
        });
    }

    /**
     * @param ServiceContainer $container
     */
    private function setupMatchers(ServiceContainer $container)
    {
        $container->set('matchers.identity', function (ServiceContainer $c) {
            return new Matcher\IdentityMatcher($c->get('formatter.presenter'));
        });
        $container->set('matchers.comparison', function (ServiceContainer $c) {
            return new Matcher\ComparisonMatcher($c->get('formatter.presenter'));
        });
        $container->set('matchers.throwm', function (ServiceContainer $c) {
            return new Matcher\ThrowMatcher($c->get('unwrapper'), $c->get('formatter.presenter'), new ReflectionFactory());
        });
        $container->set('matchers.type', function (ServiceContainer $c) {
            return new Matcher\TypeMatcher($c->get('formatter.presenter'));
        });
        $container->set('matchers.object_state', function (ServiceContainer $c) {
            return new Matcher\ObjectStateMatcher($c->get('formatter.presenter'));
        });
        $container->set('matchers.scalar', function (ServiceContainer $c) {
            return new Matcher\ScalarMatcher($c->get('formatter.presenter'));
        });
        $container->set('matchers.array_count', function (ServiceContainer $c) {
            return new Matcher\ArrayCountMatcher($c->get('formatter.presenter'));
        });
        $container->set('matchers.array_key', function (ServiceContainer $c) {
            return new Matcher\ArrayKeyMatcher($c->get('formatter.presenter'));
        });
        $container->set('matchers.array_key_with_value', function (ServiceContainer $c) {
            return new Matcher\ArrayKeyValueMatcher($c->get('formatter.presenter'));
        });
        $container->set('matchers.array_contain', function (ServiceContainer $c) {
            return new Matcher\ArrayContainMatcher($c->get('formatter.presenter'));
        });
        $container->set('matchers.string_start', function (ServiceContainer $c) {
            return new Matcher\StringStartMatcher($c->get('formatter.presenter'));
        });
        $container->set('matchers.string_end', function (ServiceContainer $c) {
            return new Matcher\StringEndMatcher($c->get('formatter.presenter'));
        });
        $container->set('matchers.string_regex', function (ServiceContainer $c) {
            return new Matcher\StringRegexMatcher($c->get('formatter.presenter'));
        });
        $container->set('matchers.string_contain', function (ServiceContainer $c) {
            return new Matcher\StringContainMatcher($c->get('formatter.presenter'));
        });
    }

    /**
     * @param ServiceContainer $container
     */
    private function setupRerunner(ServiceContainer $container)
    {
        $container->setShared('process.rerunner', function (ServiceContainer $c) {
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
                $c->get('phpspec.process.platform-specific-rerunners')
            );
        });
        $container->setShared('process.rerunner.platformspecific.pcntl', function (ServiceContainer $c) {
            return ReRunner\PcntlReRunner::withExecutionContext(
                $c->get('process.phpexecutablefinder'),
                $c->get('process.executioncontext')
            );
        });
        $container->setShared('process.rerunner.platformspecific.passthru', function (ServiceContainer $c) {
            return ReRunner\ProcOpenReRunner::withExecutionContext(
                $c->get('process.phpexecutablefinder'),
                $c->get('process.executioncontext')
            );
        });
        $container->setShared('process.rerunner.platformspecific.windowspassthru', function (ServiceContainer $c) {
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
        $container->setShared('process.shutdown', function (ServiceContainer $container) {

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

    private function setupArrayServices(ServiceContainer $container)
    {
        $container->setShared('phpspec.servicelist.console.commands', function (ServiceContainer $container) {
            return [
                'console.commands.run',
                'console.commands.describe',
            ];
        });

        $container->setShared('phpspec.console.commands', function (ServiceContainer $container) {
            return array_map(
                function ($serviceId) use ($container) {return $container->get($serviceId);},
                $container->get('phpspec.servicelist.console.commands')
            );
        });

        $container->setShared('phpspec.servicelist.spec-transformers', function (ServiceContainer $container) {
            if ($container->has('loader.resource_loader.spec_transformer.typehint_rewriter')) {
                return ['loader.resource_loader.spec_transformer.typehint_rewriter'];
            }
            return [];
        });

        $container->setShared('phpspec.spec-transformers', function (ServiceContainer $container) {
            return array_map(
                function ($serviceId) use ($container) {return $container->get($serviceId);},
                $container->get('phpspec.servicelist.spec-transformers')
            );
        });

        $container->setShared('phpspec.servicelist.formatter.differ-engines', function (ServiceContainer $container) {
            return [
                'formatter.presenter.differ.engines.string',
                'formatter.presenter.differ.engines.array',
                'formatter.presenter.differ.engines.object',
            ];
        });

        $container->setShared('phpspec.formatter.differ-engines', function (ServiceContainer $container) {
            return array_map(
                function ($serviceId) use ($container) {return $container->get($serviceId);},
                $container->get('phpspec.servicelist.formatter.differ-engines')
            );
        });

        $container->setShared('phpspec.servicelist.formatter.presenters', function (ServiceContainer $container) {
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

        $container->setShared('phpspec.formatter.presenters', function (ServiceContainer $container) {
            return array_map(
                function ($serviceId) use ($container) {return $container->get($serviceId);},
                $container->get('phpspec.servicelist.formatter.presenters')
            );
        });

        $container->setShared('phpspec.servicelist.event-listeners', function (ServiceContainer $container) {
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

        $container->setShared('phpspec.event-listeners', function (ServiceContainer $container) {
            return array_map(
                function ($serviceId) use ($container) {return $container->get($serviceId);},
                $container->get('phpspec.servicelist.event-listeners')
            );
        });

        $container->setShared('phpspec.servicelist.console-event-listeners', function (ServiceContainer $container) {
            return [];
        });

        $container->setShared('phpspec.console-event-listeners', function (ServiceContainer $container) {
            return array_map(
                function ($serviceId) use ($container) {return $container->get($serviceId);},
                $container->get('phpspec.servicelist.console-event-listeners')
            );
        });

        $container->setShared('phpspec.servicelist.process.platform-specific-rerunners', function (ServiceContainer $container) {
            return [
                'process.rerunner.platformspecific.pcntl',
                'process.rerunner.platformspecific.passthru',
                'process.rerunner.platformspecific.windowspassthru',
            ];
        });

        $container->setShared('phpspec.process.platform-specific-rerunners', function (ServiceContainer $container) {
            return array_map(
                function ($serviceId) use ($container) {return $container->get($serviceId);},
                $container->get('phpspec.servicelist.process.platform-specific-rerunners')
            );
        });

        $container->setShared('phpspec.servicelist.code-generators', function (ServiceContainer $container) {
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

        $container->setShared('phpspec.code-generators', function (ServiceContainer $container) {
            return array_map(
                function ($serviceId) use ($container) {return $container->get($serviceId);},
                $container->get('phpspec.servicelist.code-generators')
            );
        });

        $container->setShared('phpspec.servicelist.matchers', function (ServiceContainer $container) {
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

        $container->setShared('phpspec.matchers', function (ServiceContainer $container) {
            return array_map(
                function ($serviceId) use ($container) {return $container->get($serviceId);},
                $container->get('phpspec.servicelist.matchers')
            );
        });

        $container->setShared('phpspec.servicelist.runner-maintainers', function (ServiceContainer $container) {
            return [
                'runner.maintainers.errors',
                'runner.maintainers.collaborators',
                'runner.maintainers.let_letgo',
                'runner.maintainers.matchers',
                'runner.maintainers.subject',
            ];
        });

        $container->setShared('phpspec.runner-maintainers', function (ServiceContainer $container) {
            return array_map(
                function ($serviceId) use ($container) {return $container->get($serviceId);},
                $container->get('phpspec.servicelist.runner-maintainers')
            );
        });
    }
}
