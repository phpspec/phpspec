<?php

namespace spec\PhpSpec\Wrapper;

use PhpSpec\Formatter\Presenter\PresenterInterface;
use PhpSpec\Wrapper\Subject;
use PhpSpec\Loader\Node\ExampleNode;
use PhpSpec\Matcher\MatcherInterface;
use PhpSpec\ObjectBehavior;
use PhpSpec\Runner\MatcherManager;
use PhpSpec\Wrapper\Unwrapper;
Use Prophecy\Argument;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class SubjectSpec extends ObjectBehavior
{
    function let(MatcherManager $matchers, Unwrapper $unwrapper,
        PresenterInterface $presenter, EventDispatcherInterface $dispatcher, ExampleNode $example)
    {
        $this->beConstructedWith(new \Exception(), $matchers, $unwrapper, $presenter, $dispatcher, $example);
    }

    function it_dispatches_before_expectation_event_for_should(MatcherManager $matchers,
        Unwrapper $unwrapper, EventDispatcherInterface $dispatcher, MatcherInterface $matcher)
    {
        $unwrapper->unwrapOne(Argument::any())->willReturn(null);
        $unwrapper->unwrapAll(Argument::any())->willReturn(array());
        $matchers->find(Argument::any(), Argument::any(), Argument::any())->shouldBeCalled()->WillReturn($matcher);

        $dispatcher->dispatch(
            'beforeExpectation',
            Argument::type('PhpSpec\Event\ExpectationEvent')
        )->shouldBeCalled();

        $dispatcher->dispatch(
            'afterExpectation',
            Argument::any()
        )->shouldBeCalled();

        $this->callOnWrappedObject('should', array('beBoolean'));
    }

    function it_dispatches_after_expectation_event_for_should(MatcherManager $matchers,
        Unwrapper $unwrapper, EventDispatcherInterface $dispatcher, MatcherInterface $matcher)
    {
        $unwrapper->unwrapOne(Argument::any())->willReturn(null);
        $unwrapper->unwrapAll(Argument::any())->willReturn(array());
        $matchers->find(Argument::any(), Argument::any(), Argument::any())->shouldBeCalled()->WillReturn($matcher);

        $dispatcher->dispatch(
            'beforeExpectation',
            Argument::any()
        )->shouldBeCalled();

        $dispatcher->dispatch(
            'afterExpectation',
            Argument::type('PhpSpec\Event\ExpectationEvent')
        )->shouldBeCalled();

        $this->callOnWrappedObject('should', array('beBoolean'));
    }

    function it_dispatches_before_expectation_event_for_should_not(MatcherManager $matchers,
        Unwrapper $unwrapper, EventDispatcherInterface $dispatcher, MatcherInterface $matcher)
    {
        $unwrapper->unwrapOne(Argument::any())->willReturn(null);
        $unwrapper->unwrapAll(Argument::any())->willReturn(array());
        $matchers->find(Argument::any(), Argument::any(), Argument::any())->shouldBeCalled()->WillReturn($matcher);

        $dispatcher->dispatch(
            'beforeExpectation',
            Argument::type('PhpSpec\Event\ExpectationEvent')
        )->shouldBeCalled();

        $dispatcher->dispatch(
            'afterExpectation',
            Argument::any()
        )->shouldBeCalled();

        $this->callOnWrappedObject('shouldNot', array('beBoolean'));
    }

    function it_dispatches_after_expectation_event_for_should_not(MatcherManager $matchers,
        Unwrapper $unwrapper, EventDispatcherInterface $dispatcher, MatcherInterface $matcher)
    {
        $unwrapper->unwrapOne(Argument::any())->willReturn(null);
        $unwrapper->unwrapAll(Argument::any())->willReturn(array());
        $matchers->find(Argument::any(), Argument::any(), Argument::any())->shouldBeCalled()->WillReturn($matcher);

        $dispatcher->dispatch(
            'beforeExpectation',
            Argument::any()
        )->shouldBeCalled();

        $dispatcher->dispatch(
            'afterExpectation',
            Argument::type('PhpSpec\Event\ExpectationEvent')
        )->shouldBeCalled();

        $this->callOnWrappedObject('shouldNot', array('beBoolean'));
    }

    function it_dispatches_method_call_events(EventDispatcherInterface $dispatcher, Unwrapper $unwrapper)
    {
        $unwrapper->unwrapOne(Argument::any())->willReturn(new \Exception);
        $unwrapper->unwrapAll(Argument::any())->willReturn(array());

        $dispatcher->dispatch(
            'beforeMethodCall',
            Argument::type('PhpSpec\Event\MethodCallEvent')
        )->shouldBeCalled();

        $dispatcher->dispatch(
            'afterMethodCall',
            Argument::type('PhpSpec\Event\MethodCallEvent')
        )->shouldBeCalled();

        $this->callOnWrappedObject('callOnWrappedObject', array('getMessage'));
    }
}
