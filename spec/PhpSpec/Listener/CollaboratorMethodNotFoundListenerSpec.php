<?php

namespace spec\PhpSpec\Listener;

use PhpSpec\CodeGenerator\GeneratorManager;
use PhpSpec\Console\IO;
use PhpSpec\Event\ExampleEvent;
use PhpSpec\Event\SuiteEvent;
use PhpSpec\Exception\Locator\ResourceCreationException;
use PhpSpec\Locator\ResourceInterface;
use PhpSpec\Locator\ResourceManager;
use PhpSpec\Locator\ResourceManagerInterface;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Prophecy\Doubler\DoubleInterface;
use Prophecy\Exception\Doubler\MethodNotFoundException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;

class CollaboratorMethodNotFoundListenerSpec extends ObjectBehavior
{
    function let(
        IO $io, ResourceManagerInterface $resources, ExampleEvent $event,
        MethodNotFoundException $exception, ResourceInterface $resource, GeneratorManager $generator
    )
    {
        $this->beConstructedWith($io, $resources, $generator);
        $event->getException()->willReturn($exception);

        $io->isCodeGenerationEnabled()->willReturn(true);
        $io->askConfirmation(Argument::any())->willReturn(false);

        $resources->createResource(Argument::any())->willReturn($resource);

        $exception->getArguments()->willReturn(array());
    }

    function it_is_an_event_subscriber()
    {
        $this->shouldHaveType('Symfony\Component\EventDispatcher\EventSubscriberInterface');
    }

    function it_listens_to_afterexample_events()
    {
        $this->getSubscribedEvents()->shouldReturn(array(
            'afterExample' => array('afterExample', 10),
            'afterSuite' => array('afterSuite', -10)
        ));
    }

    function it_does_not_prompt_when_no_exception_is_thrown(IO $io, ExampleEvent $event, SuiteEvent $suiteEvent)
    {
        $event->getException()->willReturn(null);

        $this->afterExample($event);
        $this->afterSuite($suiteEvent);

        $io->askConfirmation(Argument::any())->shouldNotHaveBeenCalled();
    }

    function it_prompts_the_user_when_a_prophecy_method_exception_is_thrown(
        IO $io, ExampleEvent $event, SuiteEvent $suiteEvent, MethodNotFoundException $exception
    )
    {
        $exception->getClassname()->willReturn('spec\PhpSpec\Listener\DoubleOfInterface');
        $exception->getMethodName()->willReturn('aMethod');

        $this->afterExample($event);
        $this->afterSuite($suiteEvent);

        $io->askConfirmation(Argument::any())->shouldHaveBeenCalled();
    }

    function it_does_not_prompt_when_wrong_exception_is_thrown(IO $io, ExampleEvent $event, SuiteEvent $suiteEvent)
    {
        $event->getException()->willReturn(new RuntimeException());

        $this->afterExample($event);
        $this->afterSuite($suiteEvent);

        $io->askConfirmation(Argument::any())->shouldNotHaveBeenCalled();
    }

    function it_does_not_prompt_when_collaborator_is_not_an_interface(
        IO $io, ExampleEvent $event, SuiteEvent $suiteEvent, MethodNotFoundException $exception
    )
    {
        $exception->getClassname()->willReturn('spec\PhpSpec\Listener\DoubleOfStdClass');
        $exception->getMethodName()->willReturn('aMethod');

        $this->afterExample($event);
        $this->afterSuite($suiteEvent);

        $io->askConfirmation(Argument::any())->shouldNotHaveBeenCalled();
    }

    function it_does_not_prompt_when_code_generation_is_disabled(
        IO $io, ExampleEvent $event, SuiteEvent $suiteEvent, MethodNotFoundException $exception
    )
    {
        $io->isCodeGenerationEnabled()->willReturn(false);

        $exception->getClassname()->willReturn('spec\PhpSpec\Listener\DoubleOfInterface');
        $exception->getMethodName()->willReturn('aMethod');

        $this->afterExample($event);
        $this->afterSuite($suiteEvent);

        $io->askConfirmation(Argument::any())->shouldNotHaveBeenCalled();
    }

    function it_does_not_prompt_if_it_cannot_generate_the_resource(
        IO $io, ResourceManager $resources, ExampleEvent $event, SuiteEvent $suiteEvent, MethodNotFoundException $exception
    )
    {
        $resources->createResource(Argument::any())->willThrow(new ResourceCreationException());

        $exception->getClassname()->willReturn('spec\PhpSpec\Listener\DoubleOfInterface');
        $exception->getMethodName()->willReturn('aMethod');

        $this->afterExample($event);
        $this->afterSuite($suiteEvent);

        $io->askConfirmation(Argument::any())->shouldNotHaveBeenCalled();
    }

    function it_generates_the_method_signature_when_user_says_yes_at_prompt(
        IO $io, ExampleEvent $event, SuiteEvent $suiteEvent, MethodNotFoundException $exception,
        ResourceInterface $resource, GeneratorManager $generator
    )
    {
        $io->askConfirmation(Argument::any())->willReturn(true);

        $exception->getClassname()->willReturn('spec\PhpSpec\Listener\DoubleOfInterface');
        $exception->getMethodName()->willReturn('aMethod');

        $this->afterExample($event);
        $this->afterSuite($suiteEvent);

        $generator->generate($resource, 'method-signature', Argument::any())->shouldHaveBeenCalled();
    }

    function it_marks_the_suite_as_being_worth_rerunning_when_generation_happens(
        IO $io, ExampleEvent $event, SuiteEvent $suiteEvent, MethodNotFoundException $exception
    )
    {
        $io->askConfirmation(Argument::any())->willReturn(true);

        $exception->getClassname()->willReturn('spec\PhpSpec\Listener\DoubleOfInterface');
        $exception->getMethodName()->willReturn('aMethod');

        $this->afterExample($event);
        $this->afterSuite($suiteEvent);

        $suiteEvent->markAsWorthRerunning()->shouldHaveBeenCalled();
    }
}

interface ExampleInterface {}

class DoubleOfInterface extends \stdClass implements ExampleInterface, DoubleInterface {}

class DoubleOfStdClass extends \stdClass implements DoubleInterface {}
