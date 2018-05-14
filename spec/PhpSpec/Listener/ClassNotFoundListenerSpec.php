<?php

namespace spec\PhpSpec\Listener;

use PhpSpec\Locator\Resource;
use PhpSpec\Locator\ResourceManager;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

use PhpSpec\Console\ConsoleIO;
use PhpSpec\CodeGenerator\GeneratorManager;
use PhpSpec\Event\ExampleEvent;
use PhpSpec\Event\SuiteEvent;
use PhpSpec\Exception\Fracture\ClassNotFoundException as PhpSpecClassException;
use Prophecy\Exception\Doubler\ClassNotFoundException as ProphecyClassException;

class ClassNotFoundListenerSpec extends ObjectBehavior
{
    function let(
        ConsoleIO $io,
        ResourceManager $resourceManager,
        GeneratorManager $generatorManager,
        SuiteEvent $suiteEvent,
        ExampleEvent $exampleEvent,
        PhpSpecClassException $exception,
        Resource $resource
    )
    {
        $io->writeln(Argument::any())->should(function () {});
        $io->askConfirmation(Argument::any())->willReturn(false);

        $exception->getClassname()->willReturn('SomeClass');

        $resourceManager->createResource(Argument::cetera())->willReturn($resource);

        $this->beConstructedWith($io, $resourceManager, $generatorManager);
    }

    function it_does_not_prompt_for_class_generation_if_no_exception_was_thrown($exampleEvent, $suiteEvent, $io)
    {
        $io->isCodeGenerationEnabled()->willReturn(true);

        $this->afterExample($exampleEvent);
        $this->afterSuite($suiteEvent);

        $io->askConfirmation(Argument::any())->shouldNotBeenCalled();
    }

    function it_does_not_prompt_for_class_generation_if_non_class_exception_was_thrown($exampleEvent, $suiteEvent, $io, \InvalidArgumentException $invalidArgumentException)
    {
        $exampleEvent->getException()->willReturn($invalidArgumentException);
        $io->isCodeGenerationEnabled()->willReturn(true);

        $this->afterExample($exampleEvent);
        $this->afterSuite($suiteEvent);

        $io->askConfirmation(Argument::any())->shouldNotBeenCalled();
    }

    function it_prompts_for_class_generation_if_prophecy_classnotfoundexception_was_thrown_and_input_is_interactive($exampleEvent, $suiteEvent, $io, ProphecyClassException $prophecyException)
    {
        $exampleEvent->getException()->willReturn($prophecyException);
        $io->isCodeGenerationEnabled()->willReturn(true);

        $io->askConfirmation(Argument::any())->shouldBeCalled()->willReturn(false);

        $this->afterExample($exampleEvent);
        $this->afterSuite($suiteEvent);
    }

    function it_prompts_for_method_generation_if_phpspec_classnotfoundexception_was_thrown_and_input_is_interactive($exampleEvent, $suiteEvent, $io, PhpSpecClassException $exception)
    {
        $exampleEvent->getException()->willReturn($exception);
        $io->isCodeGenerationEnabled()->willReturn(true);

        $io->askConfirmation(Argument::any())->shouldBeCalled()->willReturn(false);

        $this->afterExample($exampleEvent);
        $this->afterSuite($suiteEvent);
    }

    function it_does_not_prompt_for_class_generation_if_input_is_not_interactive($exampleEvent, $suiteEvent, $io, PhpSpecClassException $exception)
    {
        $exampleEvent->getException()->willReturn($exception);
        $io->isCodeGenerationEnabled()->willReturn(false);

        $this->afterExample($exampleEvent);
        $this->afterSuite($suiteEvent);

        $io->askConfirmation(Argument::any())->shouldNotBeenCalled();
    }
}
