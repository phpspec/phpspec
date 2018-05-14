<?php

namespace spec\PhpSpec\Listener;

use PhpSpec\CodeGenerator\GeneratorManager;
use PhpSpec\Console\ConsoleIO;
use PhpSpec\Event\ExampleEvent;
use PhpSpec\Event\SuiteEvent;
use PhpSpec\Exception\Fracture\CollaboratorNotFoundException;
use PhpSpec\Locator\Resource;
use PhpSpec\Locator\ResourceManager;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class CollaboratorNotFoundListenerSpec extends ObjectBehavior
{
    function let(
        ConsoleIO $io, CollaboratorNotFoundException $exception, ExampleEvent $exampleEvent,
        ResourceManager $resources, GeneratorManager $generator, Resource $resource
    )
    {
        $this->beConstructedWith($io, $resources, $generator);

        $resources->createResource(Argument::any())->willReturn($resource);
        $resource->getSpecNamespace()->willReturn('spec');

        $exampleEvent->getException()->willReturn($exception);
        $exception->getCollaboratorName()->willReturn('Example\ExampleClass');

        $io->isCodeGenerationEnabled()->willReturn(true);
        $io->askConfirmation(Argument::any())->willReturn(false);
        $io->writeln(Argument::any())->should(function() {});
    }

    function it_listens_to_afterexample_and_aftersuite_events()
    {
        $this->getSubscribedEvents()->shouldReturn(array(
            'afterExample' => array('afterExample', 10),
            'afterSuite'   => array('afterSuite', -10)
        ));
    }

    function it_prompts_to_generate_missing_collaborator(
        ConsoleIO $io, ExampleEvent $exampleEvent, SuiteEvent $suiteEvent
    )
    {
        $this->afterExample($exampleEvent);
        $this->afterSuite($suiteEvent);

        $io->askConfirmation(
            'Would you like me to generate an interface `Example\ExampleClass` for you?'
        )->shouldHaveBeenCalled();
    }

    function it_does_not_prompt_to_generate_when_there_was_no_exception(
        ConsoleIO $io, ExampleEvent $exampleEvent, SuiteEvent $suiteEvent
    )
    {
        $exampleEvent->getException()->willReturn(null);

        $this->afterExample($exampleEvent);
        $this->afterSuite($suiteEvent);

        $io->askConfirmation(Argument::any())->shouldNotHaveBeenCalled();
    }

    function it_does_not_prompt_to_generate_when_there_was_an_exception_of_the_wrong_type(
        ConsoleIO $io, ExampleEvent $exampleEvent, SuiteEvent $suiteEvent, \InvalidArgumentException $otherException
    )
    {
        $exampleEvent->getException()->willReturn($otherException);

        $this->afterExample($exampleEvent);
        $this->afterSuite($suiteEvent);

        $io->askConfirmation(Argument::any())->shouldNotHaveBeenCalled();
    }

    function it_does_not_prompt_when_code_generation_is_disabled(
        ConsoleIO $io, ExampleEvent $exampleEvent, SuiteEvent $suiteEvent
    )
    {
        $io->isCodeGenerationEnabled()->willReturn(false);

        $this->afterExample($exampleEvent);
        $this->afterSuite($suiteEvent);

        $io->askConfirmation(Argument::any())->shouldNotHaveBeenCalled();
    }

    function it_does_not_prompt_when_collaborator_is_in_spec_namespace(
        ConsoleIO $io, ExampleEvent $exampleEvent, SuiteEvent $suiteEvent, CollaboratorNotFoundException $exception
    )
    {
        $exception->getCollaboratorName()->willReturn('spec\Example\ExampleClass');

        $this->afterExample($exampleEvent);
        $this->afterSuite($suiteEvent);

        $io->askConfirmation(Argument::any())->shouldNotHaveBeenCalled();
    }

    function it_generates_interface_when_prompt_is_answered_with_yes(
        ConsoleIO $io, ExampleEvent $exampleEvent, SuiteEvent $suiteEvent,
        GeneratorManager $generator, Resource $resource
    )
    {
        $io->askConfirmation(
            'Would you like me to generate an interface `Example\ExampleClass` for you?'
        )->willReturn(true);

        $this->afterExample($exampleEvent);
        $this->afterSuite($suiteEvent);

        $generator->generate($resource, 'interface')->shouldHaveBeenCalled();
        $suiteEvent->markAsWorthRerunning()->shouldHaveBeenCalled();
    }

    function it_does_not_generate_interface_when_prompt_is_answered_with_no(
        ConsoleIO $io, ExampleEvent $exampleEvent, SuiteEvent $suiteEvent,
        GeneratorManager $generator
    )
    {
        $io->askConfirmation(
            'Would you like me to generate an interface `Example\ExampleClass` for you?'
        )->willReturn(false);

        $this->afterExample($exampleEvent);
        $this->afterSuite($suiteEvent);

        $generator->generate(Argument::cetera())->shouldNotHaveBeenCalled();
        $suiteEvent->markAsWorthRerunning()->shouldNotHaveBeenCalled();
    }
}
