<?php

namespace spec\PhpSpec\Listener;

use PhpSpec\CodeGenerator\GeneratorManager;
use PhpSpec\Console\ConsoleIO;
use PhpSpec\Event\ExampleEvent;
use PhpSpec\Event\SuiteEvent;
use PhpSpec\Exception\Fracture\NamedConstructorNotFoundException;
use PhpSpec\Locator\Resource;
use PhpSpec\Locator\ResourceManager;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class NamedConstructorNotFoundListenerSpec extends ObjectBehavior
{
    function let(
        ConsoleIO $io,
        ResourceManager $resourceManager,
        Resource $resource,
        GeneratorManager $generatorManager,
        SuiteEvent $suiteEvent,
        ExampleEvent $exampleEvent,
        NamedConstructorNotFoundException $exception)
    {
        $io->writeln(Argument::any())->should(function() {});;
        $io->askConfirmation(Argument::any())->willReturn(false);

        $this->beConstructedWith($io, $resourceManager, $generatorManager);

        $exception->getMethodName()->willReturn('someMethod');
        $exception->getSubject()->willReturn(new \stdClass());
        $exception->getArguments()->willReturn([]);

        $resourceManager->createResource(Argument::cetera())->willReturn($resource);
    }

    function it_does_not_prompt_for_method_generation_if_no_exception_was_thrown($exampleEvent, $suiteEvent, $io)
    {
        $io->isCodeGenerationEnabled()->willReturn(true);

        $this->afterExample($exampleEvent);
        $this->afterSuite($suiteEvent);

        $io->askConfirmation(Argument::any())->shouldNotBeenCalled();
    }

    function it_does_not_prompt_for_method_generation_if_non_namedconstructornotfoundexception_was_thrown($exampleEvent, $suiteEvent, $io, \InvalidArgumentException $invalidArgumentException)
    {
        $exampleEvent->getException()->willReturn($invalidArgumentException);
        $io->isCodeGenerationEnabled()->willReturn(true);

        $this->afterExample($exampleEvent);
        $this->afterSuite($suiteEvent);

        $io->askConfirmation(Argument::any())->shouldNotBeenCalled();
    }

    function it_prompts_for_method_generation_if_namedconstructornotfoundexception_was_thrown_and_input_is_interactive($exampleEvent, $suiteEvent, $io, NamedConstructorNotFoundException $exception)
    {
        $exampleEvent->getException()->willReturn($exception);
        $io->isCodeGenerationEnabled()->willReturn(true);
        $io->askConfirmation(Argument::any())->willReturn(false);

        $exception->getSubject()->willReturn(new \stdClass());
        $exception->getMethodName()->willReturn('');
        $exception->getArguments()->willReturn([]);

        $this->afterExample($exampleEvent);
        $this->afterSuite($suiteEvent);

        $io->askConfirmation(Argument::any())->shouldHaveBeenCalled();
    }

    function it_does_not_prompt_for_method_generation_if_input_is_not_interactive($exampleEvent, $suiteEvent, $io, NamedConstructorNotFoundException $exception)
    {
        $exampleEvent->getException()->willReturn($exception);
        $io->isCodeGenerationEnabled()->willReturn(false);

        $exception->getSubject()->willReturn(new \stdClass());
        $exception->getMethodName()->willReturn('');
        $exception->getArguments()->willReturn([]);

        $this->afterExample($exampleEvent);
        $this->afterSuite($suiteEvent);

        $io->askConfirmation(Argument::any())->shouldNotBeenCalled();
    }
}
