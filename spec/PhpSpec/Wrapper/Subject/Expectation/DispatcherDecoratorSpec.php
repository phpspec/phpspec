<?php

namespace spec\PhpSpec\Wrapper\Subject\Expectation;

use PhpSpec\Event\ExpectationEvent;
use PhpSpec\Loader\Node\ExampleNode;

use PhpSpec\Matcher\MatcherInterface;
use PhpSpec\ObjectBehavior;
use PhpSpec\Wrapper\Subject\Expectation\ExpectationInterface;
use Prophecy\Argument;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class DispatcherDecoratorSpec extends ObjectBehavior
{
    function let(ExpectationInterface $expectation, EventDispatcherInterface $dispatcher, MatcherInterface $matcher, ExampleNode $example)
    {
        $this->beConstructedWith($expectation, $dispatcher, $matcher, $example);
    }

    function it_implements_the_interface_of_the_decorated()
    {
        $this->shouldImplement('PhpSpec\Wrapper\Subject\Expectation\ExpectationInterface');
    }

    function it_dispatches_before_and_after_events(EventDispatcherInterface $dispatcher)
    {
        $alias = 'be';
        $subject = new \stdClass();
        $arguments = array();

        $dispatcher->dispatch('beforeExpectation', Argument::type('PhpSpec\Event\ExpectationEvent'))->shouldBeCalled();
        $dispatcher->dispatch('afterExpectation', Argument::which('getResult', ExpectationEvent::PASSED))->shouldBeCalled();
        $this->match($alias, $subject, $arguments);
    }

    function it_decorates_expectation_with_failed_event(ExpectationInterface $expectation, EventDispatcherInterface $dispatcher)
    {
        $alias = 'be';
        $subject = new \stdClass();
        $arguments = array();

        $expectation->match(Argument::cetera())->willThrow('PhpSpec\Exception\Example\FailureException');

        $dispatcher->dispatch('beforeExpectation', Argument::type('PhpSpec\Event\ExpectationEvent'))->shouldBeCalled();
        $dispatcher->dispatch('afterExpectation', Argument::which('getResult', ExpectationEvent::FAILED))->shouldBeCalled();

        $this->shouldThrow('PhpSpec\Exception\Example\FailureException')->duringMatch($alias, $subject, $arguments);
    }

    function it_decorates_expectation_with_broken_event(ExpectationInterface $expectation, EventDispatcherInterface $dispatcher)
    {
        $alias = 'be';
        $subject = new \stdClass();
        $arguments = array();

        $expectation->match(Argument::cetera())->willThrow('\RuntimeException');

        $dispatcher->dispatch('beforeExpectation', Argument::type('PhpSpec\Event\ExpectationEvent'))->shouldBeCalled();
        $dispatcher->dispatch('afterExpectation', Argument::which('getResult', ExpectationEvent::BROKEN))->shouldBeCalled();

        $this->shouldThrow('\RuntimeException')->duringMatch($alias, $subject, $arguments);
    }
}
