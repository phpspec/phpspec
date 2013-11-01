<?php

namespace spec\PhpSpec\Wrapper\Subject;

use PhpSpec\Matcher\MatcherInterface;
use PhpSpec\ObjectBehavior;
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

    function it_creates_positive_expectations(MatcherManager $matchers, MatcherInterface $matcher)
    {
        $matchers->find(Argument::cetera())->willReturn($matcher);

        $this->create('shouldBe', new \stdClass())->shouldHaveType('PhpSpec\Wrapper\Subject\Expectation\Positive');
    }

    function it_creates_negative_expectations(MatcherManager $matchers, MatcherInterface $matcher)
    {
        $matchers->find(Argument::cetera())->willReturn($matcher);

        $this->create('shouldNotbe', new \stdClass())->shouldHaveType('PhpSpec\Wrapper\Subject\Expectation\Negative');
    }

    function it_creates_positive_exceptions_expectations(MatcherManager $matchers, MatcherInterface $matcher)
    {
        $matchers->find(Argument::cetera())->willReturn($matcher);

        $this->create('shouldThrow', new \stdClass())->shouldHaveType('PhpSpec\Wrapper\Subject\Expectation\PositiveException');
    }

    function it_creates_negative_exceptions_expectations(MatcherManager $matchers, MatcherInterface $matcher)
    {
        $matchers->find(Argument::cetera())->willReturn($matcher);

        $this->create('shouldNotThrow', new \stdClass())->shouldHaveType('PhpSpec\Wrapper\Subject\Expectation\NegativeException');
    }
}
