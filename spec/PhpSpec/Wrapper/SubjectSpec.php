<?php

namespace spec\PhpSpec\Wrapper;

use PhpSpec\Formatter\Presenter\PresenterInterface;
use PhpSpec\Exception\Example\FailureException;
use PhpSpec\Event\ExpectationEvent;
use PhpSpec\Loader\Node\ExampleNode;
use PhpSpec\Matcher\MatcherInterface;
use PhpSpec\ObjectBehavior;
use PhpSpec\Runner\MatcherManager;
use PhpSpec\Wrapper\Unwrapper;
Use Prophecy\Argument;
use RuntimeException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class SubjectSpec extends ObjectBehavior
{
    function let(MatcherManager $matchers, Unwrapper $unwrapper,
        PresenterInterface $presenter, EventDispatcherInterface $dispatcher, ExampleNode $example)
    {
        $this->beConstructedWith(new \Exception(), $matchers, $unwrapper, $presenter, $dispatcher, $example);
    }

    function it_dispatches_expectation_events_for_should(MatcherManager $matchers,
        Unwrapper $unwrapper, EventDispatcherInterface $dispatcher, MatcherInterface $matcher)
    {
        $unwrapper->unwrapOne(Argument::any())->willReturn(null);
        $unwrapper->unwrapAll(Argument::any())->willReturn(array());
        $matchers->find(Argument::cetera())->shouldBeCalled()->WillReturn($matcher);

        $dispatcher->dispatch(
            'beforeExpectation',
            Argument::type('PhpSpec\Event\ExpectationEvent')
        )->shouldBeCalled();

        $dispatcher->dispatch(
            'afterExpectation',
            Argument::which('getResult', ExpectationEvent::PASSED)
        )->shouldBeCalled();

        $this->callOnWrappedObject('should', array('beBoolean'));
    }

    function it_dispatches_after_expectation_event_with_failed_status_if_matcher_throws_exception_for_should(
        MatcherManager $matchers, Unwrapper $unwrapper, EventDispatcherInterface $dispatcher,
        MatcherInterface $matcher)
    {
        $unwrapper->unwrapOne(Argument::any())->willReturn(null);
        $unwrapper->unwrapAll(Argument::any())->willReturn(array());
        $matchers->find(Argument::cetera())->shouldBeCalled()->WillReturn($matcher);
        $matcher->positiveMatch(Argument::cetera())
            ->willThrow('PhpSpec\Exception\Example\FailureException');

        $dispatcher->dispatch(
            'beforeExpectation',
            Argument::type('PhpSpec\Event\ExpectationEvent')
        )->shouldBeCalled();

        $dispatcher->dispatch(
            'afterExpectation',
            Argument::which('getResult', ExpectationEvent::FAILED)
        )->shouldBeCalled();

        try {
            $this->callOnWrappedObject('should', array('beBoolean'));
        } catch (FailureException $e) {

        }
    }

    function it_dispatches_after_expectation_event_with_broken_status_if_throws_exception_for_should(
        MatcherManager $matchers, Unwrapper $unwrapper, EventDispatcherInterface $dispatcher,
        MatcherInterface $matcher)
    {
        $unwrapper->unwrapOne(Argument::any())->willReturn(null);
        $unwrapper->unwrapAll(Argument::any())->willReturn(array());
        $matchers->find(Argument::cetera())->shouldBeCalled()->WillReturn($matcher);
        $matcher->positiveMatch(Argument::cetera())
            ->willThrow('RuntimeException');

        $dispatcher->dispatch(
            'beforeExpectation',
            Argument::type('PhpSpec\Event\ExpectationEvent')
        )->shouldBeCalled();

        $dispatcher->dispatch(
            'afterExpectation',
            Argument::which('getResult', ExpectationEvent::BROKEN)
        )->shouldBeCalled();

        try {
            $this->callOnWrappedObject('should', array('beBoolean'));
        } catch (RuntimeException $e) {

        }
    }

    function it_dispatches_expectation_events_for_should_not(MatcherManager $matchers,
        Unwrapper $unwrapper, EventDispatcherInterface $dispatcher, MatcherInterface $matcher)
    {
        $unwrapper->unwrapOne(Argument::any())->willReturn(null);
        $unwrapper->unwrapAll(Argument::any())->willReturn(array());
        $matchers->find(Argument::cetera())->shouldBeCalled()->WillReturn($matcher);

        $dispatcher->dispatch(
            'beforeExpectation',
            Argument::type('PhpSpec\Event\ExpectationEvent')
        )->shouldBeCalled();

        $dispatcher->dispatch(
            'afterExpectation',
            Argument::which('getResult', ExpectationEvent::PASSED)
        )->shouldBeCalled();

        $this->callOnWrappedObject('shouldNot', array('beBoolean'));
    }

    function it_dispatches_after_expectation_event_with_failed_status_if_matcher_throws_exception_for_should_not(
        MatcherManager $matchers, Unwrapper $unwrapper, EventDispatcherInterface $dispatcher,
        MatcherInterface $matcher)
    {
        $unwrapper->unwrapOne(Argument::any())->willReturn(null);
        $unwrapper->unwrapAll(Argument::any())->willReturn(array());
        $matchers->find(Argument::cetera())->shouldBeCalled()->WillReturn($matcher);
        $matcher->negativeMatch(Argument::cetera())->willThrow('PhpSpec\Exception\Example\FailureException');

        $dispatcher->dispatch(
            'beforeExpectation',
            Argument::type('PhpSpec\Event\ExpectationEvent')
        )->shouldBeCalled();

        $dispatcher->dispatch(
            'afterExpectation',
            Argument::which('getResult', ExpectationEvent::FAILED)
        )->shouldBeCalled();

        try {
            $this->callOnWrappedObject('shouldNot', array('beBoolean'));
        } catch (FailureException $e) {

        }
    }

    function it_dispatches_after_expectation_event_with_broken_status_if_throws_exception_for_should_not(
        MatcherManager $matchers, Unwrapper $unwrapper, EventDispatcherInterface $dispatcher,
        MatcherInterface $matcher)
    {
        $unwrapper->unwrapOne(Argument::any())->willReturn(null);
        $unwrapper->unwrapAll(Argument::any())->willReturn(array());
        $matchers->find(Argument::cetera())->shouldBeCalled()->WillReturn($matcher);
        $matcher->negativeMatch(Argument::cetera())
            ->willThrow('RuntimeException');

        $dispatcher->dispatch(
            'beforeExpectation',
            Argument::type('PhpSpec\Event\ExpectationEvent')
        )->shouldBeCalled();

        $dispatcher->dispatch(
            'afterExpectation',
            Argument::which('getResult', ExpectationEvent::BROKEN)
        )->shouldBeCalled();

        try {
            $this->callOnWrappedObject('shouldNot', array('beBoolean'));
        } catch (RuntimeException $e) {

        }
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
