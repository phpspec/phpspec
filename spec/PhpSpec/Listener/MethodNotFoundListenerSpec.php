<?php

namespace spec\PhpSpec\Listener;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

use PhpSpec\Console\ConsoleIO;
use PhpSpec\CodeGenerator\GeneratorManager;
use PhpSpec\Event\ExampleEvent;
use PhpSpec\Event\SuiteEvent;
use PhpSpec\Exception\Fracture\MethodNotFoundException;
use PhpSpec\Locator\ResourceManager;
use PhpSpec\Util\NameChecker;

class MethodNotFoundListenerSpec extends ObjectBehavior
{
    function let(
        ConsoleIO $io,
        ResourceManager $resourceManager,
        GeneratorManager $generatorManager,
        SuiteEvent $suiteEvent,
        ExampleEvent $exampleEvent,
        NameChecker $nameChecker
    ) {
        $io->writeln(Argument::any())->willReturn();
        $io->askConfirmation(Argument::any())->willReturn();

        $this->beConstructedWith($io, $resourceManager, $generatorManager, $nameChecker);
        $io->isCodeGenerationEnabled()->willReturn(true);
    }

    function it_does_not_prompt_for_method_generation_if_no_exception_was_thrown($exampleEvent, $suiteEvent, $io)
    {
        $this->afterExample($exampleEvent);
        $this->afterSuite($suiteEvent);

        $io->askConfirmation(Argument::any())->shouldNotBeenCalled();
    }

    function it_does_not_prompt_for_method_generation_if_non_methodnotfoundexception_was_thrown($exampleEvent, $suiteEvent, $io, \InvalidArgumentException $exception)
    {
        $exampleEvent->getException()->willReturn($exception);

        $this->afterExample($exampleEvent);
        $this->afterSuite($suiteEvent);

        $io->askConfirmation(Argument::any())->shouldNotBeenCalled();
    }

    function it_prompts_for_method_generation_if_methodnotfoundexception_was_thrown_and_input_is_interactive(
        $exampleEvent,
        $suiteEvent,
        $io,
        NameChecker $nameChecker
    ) {
        $exception = new MethodNotFoundException('Error', new \stdClass(), 'bar');

        $exampleEvent->getException()->willReturn($exception);
        $nameChecker->isNameValid('bar')->willReturn(true);

        $this->afterExample($exampleEvent);
        $this->afterSuite($suiteEvent);

        $io->askConfirmation(Argument::any())->shouldHaveBeenCalled();
    }

    function it_does_not_prompt_for_method_generation_if_input_is_not_interactive($exampleEvent, $suiteEvent, $io, MethodNotFoundException $exception)
    {
        $exampleEvent->getException()->willReturn($exception);
        $io->isCodeGenerationEnabled()->willReturn(false);

        $this->afterExample($exampleEvent);
        $this->afterSuite($suiteEvent);

        $io->askConfirmation(Argument::any())->shouldNotBeenCalled();
    }

    function it_warns_when_method_name_is_reserved(
        $exampleEvent,
        $suiteEvent,
        ConsoleIO $io,
        NameChecker $nameChecker
    ) {
        $this->callAfterExample($exampleEvent, $nameChecker, 'throw', false);

        $io->writeBrokenCodeBlock("I cannot generate the method 'throw' for you because it is a reserved keyword", 2)->shouldBeCalled();

        $this->afterSuite($suiteEvent);
    }

    function it_prompts_and_warns_when_one_method_name_is_correct_but_other_reserved(
        $exampleEvent,
        SuiteEvent $suiteEvent,
        ConsoleIO $io,
        NameChecker $nameChecker
    ) {
        $this->callAfterExample($exampleEvent, $nameChecker, 'throw', false);
        $this->callAfterExample($exampleEvent, $nameChecker, 'foo');

        $io->writeBrokenCodeBlock("I cannot generate the method 'throw' for you because it is a reserved keyword", 2)->shouldBeCalled();
        $io->askConfirmation('Do you want me to create `stdClass::foo()` for you?')->shouldBeCalled();
        $suiteEvent->markAsNotWorthRerunning()->shouldBeCalled();

        $this->afterSuite($suiteEvent);
    }

    private function callAfterExample($exampleEvent, $nameChecker, $method, $isNameValid = true)
    {
        $exception = new MethodNotFoundException('Error', new \stdClass(), $method);
        $exampleEvent->getException()->willReturn($exception);
        $nameChecker->isNameValid($method)->willReturn($isNameValid);

        $this->afterExample($exampleEvent);
    }
}
