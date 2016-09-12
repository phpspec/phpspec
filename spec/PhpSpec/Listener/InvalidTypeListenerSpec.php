<?php

namespace spec\PhpSpec\Listener;

use PhpSpec\CodeGenerator\GeneratorManager;
use PhpSpec\Console\ConsoleIO;
use PhpSpec\Event\ExampleEvent;
use PhpSpec\Event\SuiteEvent;
use PhpSpec\Exception\Example\TypeFailureException;
use PhpSpec\Listener\InvalidTypeListener;
use PhpSpec\Locator\ResourceManager;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class InvalidTypeListenerSpec extends ObjectBehavior
{
    function let(ConsoleIO $io, GeneratorManager $generator, ResourceManager $resources)
    {
        $this->beConstructedWith($io, $generator, $resources);
    }

    function it_is_initializable()
    {
        $this->shouldHaveType(InvalidTypeListener::class);
    }

    function it_does_not_prompt_for_method_generation_if_no_exception_was_thrown(ExampleEvent $exampleEvent, SuiteEvent $suiteEvent, ConsoleIO $io)
    {
        $io->isCodeGenerationEnabled()->willReturn(true);

        $this->afterExample($exampleEvent);
        $this->afterSuite($suiteEvent);

        $io->askConfirmation(Argument::any())->shouldNotBeenCalled();
    }

    function it_does_not_prompt_for_method_generation_if_wrong_exception_was_thrown(ExampleEvent $exampleEvent, SuiteEvent $suiteEvent, ConsoleIO $io)
    {
        $exampleEvent->getException();
        $io->isCodeGenerationEnabled()->willReturn(true);

        $this->afterExample($exampleEvent);
        $this->afterSuite($suiteEvent);

        $io->askConfirmation(Argument::any())->shouldNotBeenCalled();
    }

    function it_prompts_for_method_generation_if_correct_exception_was_thrown_and_input_is_interactive(
        ExampleEvent $exampleEvent,
        SuiteEvent $suiteEvent,
        ConsoleIO $io
    ) {
        $exception = new TypeFailureException('Error', new \stdClass(), \IteratorAggregate::class);

        $exampleEvent->getException()->willReturn($exception);
        $io->isCodeGenerationEnabled()->willReturn(true);

        $this->afterExample($exampleEvent);
        $this->afterSuite($suiteEvent);

        $io->askConfirmation(Argument::any())->shouldHaveBeenCalled();
    }

    function it_does_not_prompt_for_method_generation_if_code_generation_is_not_enabled(
        ExampleEvent $exampleEvent,
        SuiteEvent $suiteEvent,
        ConsoleIO $io
    ) {
        $exception = new TypeFailureException('Error', new \stdClass(), \IteratorAggregate::class);

        $exampleEvent->getException()->willReturn($exception);
        $io->isCodeGenerationEnabled()->willReturn(false);

        $this->afterExample($exampleEvent);
        $this->afterSuite($suiteEvent);

        $io->askConfirmation(Argument::any())->shouldNotBeenCalled();
    }
}
