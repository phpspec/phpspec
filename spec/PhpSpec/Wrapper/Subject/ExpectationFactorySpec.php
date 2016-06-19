<?php

namespace spec\PhpSpec\Wrapper\Subject;

use PhpSpec\Matcher\Matcher;
use PhpSpec\ObjectBehavior;
use PhpSpec\Wrapper\Subject;
use Prophecy\Argument;

use PhpSpec\Loader\Node\ExampleNode;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use PhpSpec\Runner\MatcherManager;

class ExpectationFactorySpec extends ObjectBehavior
{
    function let(ExampleNode $example, EventDispatcherInterface $dispatcher, MatcherManager $matchers)
    {
        $this->beConstructedWith($example, $dispatcher, $matchers);
    }

    function it_creates_positive_expectations(MatcherManager $matchers, Matcher $matcher, Subject $subject)
    {
        $matchers->find(Argument::cetera())->willReturn($matcher);

        $subject->__call('getWrappedObject', array())->willReturn(new \stdClass());
        $decoratedExpecation = $this->create('shouldBe', $subject);

        $decoratedExpecation->shouldHaveType('PhpSpec\Wrapper\Subject\Expectation\Decorator');
        $decoratedExpecation->getNestedExpectation()->shouldHaveType('PhpSpec\Wrapper\Subject\Expectation\Positive');
    }

    function it_creates_negative_expectations(MatcherManager $matchers, Matcher $matcher, Subject $subject)
    {
        $matchers->find(Argument::cetera())->willReturn($matcher);

        $subject->__call('getWrappedObject', array())->willReturn(new \stdClass());
        $decoratedExpecation = $this->create('shouldNotbe', $subject);

        $decoratedExpecation->shouldHaveType('PhpSpec\Wrapper\Subject\Expectation\Decorator');
        $decoratedExpecation->getNestedExpectation()->shouldHaveType('PhpSpec\Wrapper\Subject\Expectation\Negative');
    }

    function it_creates_positive_throw_expectations(MatcherManager $matchers, Matcher $matcher, Subject $subject)
    {
        $matchers->find(Argument::cetera())->willReturn($matcher);

        $subject->__call('getWrappedObject', array())->willReturn(new \stdClass());
        $expectation = $this->create('shouldThrow', $subject);

        $expectation->shouldHaveType('PhpSpec\Wrapper\Subject\Expectation\PositiveThrow');
    }

    function it_creates_negative_throw_expectations(MatcherManager $matchers, Matcher $matcher, Subject $subject)
    {
        $matchers->find(Argument::cetera())->willReturn($matcher);

        $subject->__call('getWrappedObject', array())->willReturn(new \stdClass());
        $expectation = $this->create('shouldNotThrow', $subject);

        $expectation->shouldHaveType('PhpSpec\Wrapper\Subject\Expectation\NegativeThrow');
    }

    function it_creates_positive_trigger_expectations(MatcherManager $matchers, Matcher $matcher, Subject $subject)
    {
        $matchers->find(Argument::cetera())->willReturn($matcher);

        $subject->__call('getWrappedObject', array())->willReturn(new \stdClass());
        $expectation = $this->create('shouldTrigger', $subject);

        $expectation->shouldHaveType('PhpSpec\Wrapper\Subject\Expectation\PositiveTrigger');
    }

    function it_creates_negative_trigger_expectations(MatcherManager $matchers, Matcher $matcher, Subject $subject)
    {
        $matchers->find(Argument::cetera())->willReturn($matcher);

        $subject->__call('getWrappedObject', array())->willReturn(new \stdClass());
        $expectation = $this->create('shouldNotTrigger', $subject);

        $expectation->shouldHaveType('PhpSpec\Wrapper\Subject\Expectation\NegativeTrigger');
    }
}
