<?php

namespace spec\PhpSpec\Listener;

use PhpSpec\CodeGenerator\GeneratorManager;
use PhpSpec\Console\ConsoleIO;
use PhpSpec\Event\ExampleEvent;
use PhpSpec\Event\SuiteEvent;
use PhpSpec\Exception\Locator\ResourceCreationException;
use PhpSpec\Locator\Resource;
use PhpSpec\Locator\ResourceManager;
use PhpSpec\ObjectBehavior;
use PhpSpec\Util\NameChecker;
use Prophecy\Argument;
use Prophecy\Doubler\DoubleInterface;
use Prophecy\Exception\Doubler\MethodNotFoundException;

class CollaboratorMethodNotFoundListenerSpec extends ObjectBehavior
{
    function let(
        ConsoleIO $io, ResourceManager $resources, ExampleEvent $event,
        MethodNotFoundException $exception, Resource $resource, GeneratorManager $generator,
        NameChecker $nameChecker
    ) {
        $this->beConstructedWith($io, $resources, $generator, $nameChecker);
        $event->getException()->willReturn($exception);

        $io->isCodeGenerationEnabled()->willReturn(true);
        $io->askConfirmation(Argument::any())->willReturn(false);

        $resources->createResource(Argument::any())->willReturn($resource);

        $exception->getArguments()->willReturn(array());
        $nameChecker->isNameValid('aMethod')->willReturn(true);
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

    function it_does_not_prompt_when_no_exception_is_thrown(ConsoleIO $io, ExampleEvent $event, SuiteEvent $suiteEvent)
    {
        $event->getException()->willReturn(null);

        $this->afterExample($event);
        $this->afterSuite($suiteEvent);

        $io->askConfirmation(Argument::any())->shouldNotHaveBeenCalled();
    }

    function it_prompts_the_user_when_a_prophecy_method_exception_is_thrown(
        ConsoleIO $io, ExampleEvent $event, SuiteEvent $suiteEvent, MethodNotFoundException $exception
    )
    {
        $exception->getClassname()->willReturn('spec\PhpSpec\Listener\DoubleOfInterface');
        $exception->getMethodName()->willReturn('aMethod');

        $this->afterExample($event);
        $this->afterSuite($suiteEvent);

        $io->askConfirmation(Argument::any())->shouldHaveBeenCalled();
    }

    function it_does_not_prompt_when_wrong_exception_is_thrown(ConsoleIO $io, ExampleEvent $event, SuiteEvent $suiteEvent)
    {
        $event->getException()->willReturn(new \RuntimeException());

        $this->afterExample($event);
        $this->afterSuite($suiteEvent);

        $io->askConfirmation(Argument::any())->shouldNotHaveBeenCalled();
    }

    function it_does_not_prompt_when_collaborator_is_not_an_interface(
        ConsoleIO $io, ExampleEvent $event, SuiteEvent $suiteEvent, MethodNotFoundException $exception
    )
    {
        $exception->getClassname()->willReturn('spec\PhpSpec\Listener\DoubleOfStdClass');
        $exception->getMethodName()->willReturn('aMethod');

        $this->afterExample($event);
        $this->afterSuite($suiteEvent);

        $io->askConfirmation(Argument::any())->shouldNotHaveBeenCalled();
    }

    function it_does_not_prompt_when_code_generation_is_disabled(
        ConsoleIO $io, ExampleEvent $event, SuiteEvent $suiteEvent, MethodNotFoundException $exception
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
        ConsoleIO $io, ResourceManager $resources, ExampleEvent $event, SuiteEvent $suiteEvent, MethodNotFoundException $exception
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
        ConsoleIO $io, ExampleEvent $event, SuiteEvent $suiteEvent, MethodNotFoundException $exception,
        Resource $resource, GeneratorManager $generator
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
        ConsoleIO $io, ExampleEvent $event, SuiteEvent $suiteEvent, MethodNotFoundException $exception
    )
    {
        $io->askConfirmation(Argument::any())->willReturn(true);

        $exception->getClassname()->willReturn('spec\PhpSpec\Listener\DoubleOfInterface');
        $exception->getMethodName()->willReturn('aMethod');

        $this->afterExample($event);
        $this->afterSuite($suiteEvent);

        $suiteEvent->markAsWorthRerunning()->shouldHaveBeenCalled();
    }

    function it_warns_if_a_method_name_is_wrong(
        ExampleEvent $event,
        SuiteEvent $suiteEvent,
        ConsoleIO $io,
        NameChecker $nameChecker
    ) {
        $exception = new MethodNotFoundException('Error', new DoubleOfInterface(), 'throw');

        $event->getException()->willReturn($exception);
        $nameChecker->isNameValid('throw')->willReturn(false);

        $io->writeBrokenCodeBlock("I cannot generate the method 'throw' for you because it is a reserved keyword", 2)->shouldBeCalled();
        $io->askConfirmation(Argument::any())->shouldNotBeCalled();

        $this->afterExample($event);
        $this->afterSuite($suiteEvent);
    }

    function it_prompts_and_warns_when_one_method_name_is_correct_but_other_reserved(
        ExampleEvent $event,
        SuiteEvent $suiteEvent,
        ConsoleIO $io,
        NameChecker $nameChecker
    ) {
        $this->callAfterExample($event, $nameChecker, 'throw', false);
        $this->callAfterExample($event, $nameChecker, 'foo');

        $io->writeBrokenCodeBlock("I cannot generate the method 'throw' for you because it is a reserved keyword", 2)->shouldBeCalled();
        $io->askConfirmation(Argument::any())->shouldBeCalled();
        $suiteEvent->markAsNotWorthRerunning()->shouldBeCalled();

        $this->afterSuite($suiteEvent);
    }

    private function callAfterExample($event, $nameChecker, $method, $isNameValid = true)
    {
        $exception = new MethodNotFoundException('Error', DoubleOfInterface::class, $method);
        $event->getException()->willReturn($exception);
        $nameChecker->isNameValid($method)->willReturn($isNameValid);

        $this->afterExample($event);
    }
}

interface ExampleInterface {}

class DoubleOfInterface extends \stdClass implements ExampleInterface, DoubleInterface {}

class DoubleOfStdClass extends \stdClass implements DoubleInterface {}
