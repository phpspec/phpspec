<?php

namespace spec\PhpSpec\Wrapper\Subject;

use PhpSpec\Loader\Node\ExampleNode;
use PhpSpec\Runner\MatcherManager;
use PhpSpec\Matcher\MatcherInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

use PhpSpec\Exception\Example\FailureException;

use PhpSpec\Event\ExpectationEvent;
use \RuntimeException;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class ExpectationSpec extends ObjectBehavior
{
    function let(ExampleNode $example, EventDispatcherInterface $dispatcher, MatcherManager $matchers)
    {
        $this->beConstructedWith(new \Exception, $example, $dispatcher, $matchers);
    }

    function it_dispatches_after_expectation_event_with_failed_status_if_matcher_throws_exception_for_should(
        MatcherManager $matchers, EventDispatcherInterface $dispatcher,
        MatcherInterface $matcher)
    {
        $matchers->find(Argument::cetera())->shouldBeCalled()->WillReturn($matcher);
        $matcher->positiveMatch(Argument::cetera())
            ->willThrow('PhpSpec\Exception\Example\FailureException');

        $dispatcher->dispatch(
            'beforeExpectation',
            Argument::type('PhpSpec\Event\ExpectationEvent')
        )->shouldBeCalled();

        $dispatcher->dispatch(
            'afterExpectation',
            Argument::any()
        )->shouldBeCalled();

        try {
            $this->positive('beBoolean');
        } catch (FailureException $e) {
            
        }
    }

    function it_dispatches_after_expectation_event_with_broken_status_if_throws_exception_for_should(
        MatcherManager $matchers, EventDispatcherInterface $dispatcher,
        MatcherInterface $matcher)
    {
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
            $this->positive('beBoolean');
        } catch (RuntimeException $e) {

        }
    }

    function it_dispatches_expectation_events_for_should_not(MatcherManager $matchers,
        EventDispatcherInterface $dispatcher, MatcherInterface $matcher)
    {
        $matchers->find(Argument::cetera())->shouldBeCalled()->WillReturn($matcher);

        $dispatcher->dispatch(
            'beforeExpectation',
            Argument::type('PhpSpec\Event\ExpectationEvent')
        )->shouldBeCalled();

        $dispatcher->dispatch(
            'afterExpectation',
            Argument::which('getResult', ExpectationEvent::PASSED)
        )->shouldBeCalled();

        $this->negative('beBoolean');
    }

    function it_dispatches_after_expectation_event_with_failed_status_if_matcher_throws_exception_for_should_not(
        MatcherManager $matchers, EventDispatcherInterface $dispatcher,
        MatcherInterface $matcher)
    {
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
            $this->negative('beBoolean');
        } catch (FailureException $e) {

        }
    }

    function it_dispatches_after_expectation_event_with_broken_status_if_throws_exception_for_should_not(
        MatcherManager $matchers, EventDispatcherInterface $dispatcher,
        MatcherInterface $matcher)
    {
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
            $this->negative('beBoolean');
        } catch (RuntimeException $e) {

        }
    }
}
