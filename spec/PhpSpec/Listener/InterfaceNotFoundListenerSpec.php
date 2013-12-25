<?php

namespace spec\PhpSpec\Listener;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class InterfaceNotFoundListenerSpec extends ObjectBehavior
{
    /**
     * @param PhpSpec\Console\IO $io
     * @param PhpSpec\Locator\ResourceManager $resourceManager
     * @param PhpSpec\CodeGenerator\GeneratorManager $generatorManager
     * @param PhpSpec\Event\SuiteEvent $suiteEvent
     * @param PhpSpec\Event\ExampleEvent $exampleEvent
     */
    function let($io, $resourceManager, $generatorManager, $suiteEvent, $exampleEvent)
    {
        $io->writeln(Argument::any())->willReturn();
        $io->askConfirmation(Argument::any())->willReturn();

        $this->beConstructedWith($io, $resourceManager, $generatorManager);
    }

    /**
     * @param PhpSpec\Event\ExampleEvent $exampleEvent
     * @param PhpSpec\Event\SuiteEvent $suiteEvent
     * @param PhpSpec\Console\IO $io
     */
    function it_does_not_prompt_for_interface_generation_if_no_exception_was_thrown($exampleEvent, $suiteEvent, $io)
    {
        $io->isCodeGenerationEnabled()->willReturn(true);

        $this->afterExample($exampleEvent);
        $this->afterSuite($suiteEvent);

        $io->askConfirmation(Argument::any())->shouldNotBeenCalled();
    }

    /**
     * @param PhpSpec\Event\ExampleEvent $exampleEvent
     * @param PhpSpec\Event\SuiteEvent $suiteEvent
     * @param PhpSpec\Console\IO $io
     * @param PhpSpec\Exception\Fracture\ClassNotFoundException $exception
     */
    function it_does_not_prompt_for_interface_generation_if_non_interface_exception_was_thrown($exampleEvent, $suiteEvent, $io, $exception)
    {
        $exampleEvent->getException()->willReturn($exception);
        $io->isCodeGenerationEnabled()->willReturn(true);

        $this->afterExample($exampleEvent);
        $this->afterSuite($suiteEvent);

        $io->askConfirmation(Argument::any())->shouldNotBeenCalled();
    }

    /**
     * @param PhpSpec\Event\ExampleEvent $exampleEvent
     * @param PhpSpec\Event\SuiteEvent $suiteEvent
     * @param PhpSpec\Console\IO $io
     * @param PhpSpec\Exception\Fracture\InterfaceNotFoundException $exception
     */
    function it_prompts_for_interface_generation_if_interfacenotfoundexception_was_thrown_and_input_is_interactive($exampleEvent, $suiteEvent, $io, $exception)
    {
        $exampleEvent->getException()->willReturn($exception);
        $io->isCodeGenerationEnabled()->willReturn(true);

        $this->afterExample($exampleEvent);
        $this->afterSuite($suiteEvent);

        $io->askConfirmation(Argument::any())->shouldHaveBeenCalled();
    }

    /**
     * @param PhpSpec\Event\ExampleEvent $exampleEvent
     * @param PhpSpec\Event\SuiteEvent $suiteEvent
     * @param PhpSpec\Console\IO $io
     * @param PhpSpec\Exception\Fracture\InterfaceNotFoundException $exception
     */
    function it_does_not_prompt_for_interface_generation_if_input_is_not_interactive($exampleEvent, $suiteEvent, $io, $exception)
    {
        $exampleEvent->getException()->willReturn($exception);
        $io->isCodeGenerationEnabled()->willReturn(false);

        $this->afterExample($exampleEvent);
        $this->afterSuite($suiteEvent);

        $io->askConfirmation(Argument::any())->shouldNotBeenCalled();
    }
}
