<?php

namespace spec\PhpSpec\Listener;

use PhpSpec\Console\IO;
use PhpSpec\Event\ExampleEvent;
use PhpSpec\Event\SuiteEvent;
use PhpSpec\Loader\Node\ExampleNode;
use PhpSpec\ObjectBehavior;
use PhpSpec\Process\RerunContext;
use Prophecy\Argument;
use ReflectionClass;
use Symfony\Component\Console\Output\OutputInterface;

class FatalSkippingListenerSpec extends ObjectBehavior
{
    function let(OutputInterface $output, RerunContext $context, ExampleEvent $exampleEvent,
        ExampleNode $exampleNode, \ReflectionMethod $reflectedFunction, ReflectionClass $reflectedClass)
    {
        $this->beConstructedWith($output, $context);
        $output->getVerbosity()->willReturn(OutputInterface::VERBOSITY_NORMAL);
        $output->setVerbosity(Argument::any())->willReturn();

        $exampleEvent->getExample()->willReturn($exampleNode);
        $exampleNode->getFunctionReflection()->willReturn($reflectedFunction);
        $reflectedFunction->getDeclaringClass()->willReturn($reflectedClass);

        $reflectedClass->getName()->willReturn('Foo');
        $reflectedFunction->getName()->willReturn('Bar');
    }

    function it_does_not_change_the_verbosity_when_there_are_no_fatals(
        OutputInterface $output, SuiteEvent $event, RerunContext $context
    )
    {
        $context->listFatalSpecs()->willReturn(array());

        $this->beforeSuite($event);

        $output->setVerbosity(Argument::any())->shouldNotHaveBeenCalled();
    }

    function it_changes_the_verbosity_to_silent_when_there_is_a_fatal(
        OutputInterface $output, SuiteEvent $event, RerunContext $context
    )
    {
        $context->listFatalSpecs()->willReturn(array(array('Foo', 'Bar')));

        $this->beforeSuite($event);

        $output->setVerbosity(OutputInterface::VERBOSITY_QUIET)->shouldHaveBeenCalled();
    }

    function it_does_not_turn_the_verbosity_back_on_when_there_are_still_more_fatals(
        OutputInterface $output, SuiteEvent $event, RerunContext $context, ExampleEvent $exampleEvent
    )
    {
        $context->listFatalSpecs()->willReturn(array(array('Foo', 'Bar'), array('Baz', 'Boz')));

        $this->beforeSuite($event);
        $this->beforeExample($exampleEvent);

        $output->setVerbosity(OutputInterface::VERBOSITY_NORMAL)->shouldNotHaveBeenCalled();
    }

    function it_does_turns_the_verbosity_back_on_when_there_are_no_more_fatals(
        OutputInterface $output, SuiteEvent $event, RerunContext $context, ExampleEvent $exampleEvent
    )
    {
        $context->listFatalSpecs()->willReturn(array(array('Foo', 'Bar')));

        $this->beforeSuite($event);
        $this->beforeExample($exampleEvent);

        $output->setVerbosity(OutputInterface::VERBOSITY_NORMAL)->shouldHaveBeenCalled();
    }

}
