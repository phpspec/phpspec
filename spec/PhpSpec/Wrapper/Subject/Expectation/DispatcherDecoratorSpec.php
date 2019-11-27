<?php

namespace spec\PhpSpec\Wrapper\Subject\Expectation;

use PhpSpec\ObjectBehavior;
use PhpSpec\Util\DispatchTrait;
use Prophecy\Argument;

use PhpSpec\Wrapper\Subject\Expectation\Expectation;
use PhpSpec\Loader\Node\ExampleNode;
use PhpSpec\Matcher\Matcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

use PhpSpec\Event\ExpectationEvent;

class DispatcherDecoratorSpec extends ObjectBehavior
{
    use DispatchTrait;

    function let(Expectation $expectation, EventDispatcherInterface $dispatcher, Matcher $matcher, ExampleNode $example)
    {
        $dispatcher->dispatch(Argument::any(), Argument::any())->willReturnArgument(0);
        $this->beConstructedWith($expectation, $dispatcher, $matcher, $example);
    }

    function it_implements_the_interface_of_the_decorated()
    {
        $this->shouldImplement('PhpSpec\Wrapper\Subject\Expectation\Expectation');
    }

    function it_dispatches_before_and_after_events(EventDispatcherInterface $dispatcher)
    {
        $alias = 'be';
        $subject = new \stdClass();
        $arguments = array();

        $this->dispatch($dispatcher, Argument::type('PhpSpec\Event\ExpectationEvent'), 'beforeExpectation')->shouldBeCalled();
        $this->dispatch($dispatcher, Argument::which('getResult', ExpectationEvent::PASSED), 'afterExpectation')->shouldBeCalled();
        $this->match($alias, $subject, $arguments);
    }

    function it_decorates_expectation_with_failed_event(Expectation $expectation, EventDispatcherInterface $dispatcher)
    {
        $alias = 'be';
        $subject = new \stdClass();
        $arguments = array();

        $expectation->match(Argument::cetera())->willThrow('PhpSpec\Exception\Example\FailureException');

        $this->dispatch($dispatcher, Argument::type('PhpSpec\Event\ExpectationEvent'), 'beforeExpectation')->shouldBeCalled();
        $this->dispatch($dispatcher, Argument::which('getResult', ExpectationEvent::FAILED), 'afterExpectation')->shouldBeCalled();

        $this->shouldThrow('PhpSpec\Exception\Example\FailureException')->duringMatch($alias, $subject, $arguments);
    }

    function it_decorates_expectation_with_broken_event(Expectation $expectation, EventDispatcherInterface $dispatcher)
    {
        $alias = 'be';
        $subject = new \stdClass();
        $arguments = array();

        $expectation->match(Argument::cetera())->willThrow('\RuntimeException');

        $this->dispatch($dispatcher, Argument::type('PhpSpec\Event\ExpectationEvent'), 'beforeExpectation')->shouldBeCalled();
        $this->dispatch($dispatcher, Argument::which('getResult', ExpectationEvent::BROKEN), 'afterExpectation')->shouldBeCalled();

        $this->shouldThrow('\RuntimeException')->duringMatch($alias, $subject, $arguments);
    }
}
