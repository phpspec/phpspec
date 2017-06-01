<?php

namespace spec\PhpSpec\Matcher;

use PhpSpec\Exception\Example\FailureException;
use PhpSpec\Formatter\Presenter\Presenter;
use PhpSpec\Matcher\Matcher;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

final class TraversableThrowMatcherSpec extends ObjectBehavior
{
    function let(Presenter $presenter)
    {
        $presenter->presentValue(Argument::any())->willReturn('traversable');

        $this->beConstructedWith($presenter);
    }

    function it_is_a_matcher()
    {
        $this->shouldBeAnInstanceOf(Matcher::class);
    }

    function it_responds_to_contain()
    {
        $this->supports('throwWhenIterating', $this->createGeneratorThrowing(null), [''])->shouldReturn(true);
    }

    function it_positive_match_something_thrown_when_any_exception_is_expected()
    {
        $this
            ->shouldNotThrow(FailureException::class)
            ->during('positiveMatch', ['throwWhenIterating', $this->createGeneratorThrowing(new \Exception()), []])
            ;
    }

    function it_does_not_positive_match_nothing_thrown_when_any_exception_is_expected()
    {
        $this
            ->shouldThrow(FailureException::class)
            ->during('positiveMatch', ['throwWhenIterating', $this->createGeneratorThrowing(null), []])
        ;
    }

    function it_positive_match_when_exception_thrown_is_same_as_expected()
    {
        $this
            ->shouldNotThrow(FailureException::class)
            ->during('positiveMatch', ['throwWhenIterating', $this->createGeneratorThrowing(new \RuntimeException()), ['\\RuntimeException']])
        ;
    }

    function it_does_not_positive_match_exception_thrown_is_not_same_as_expected()
    {
        $this
            ->shouldThrow(FailureException::class)
            ->during('positiveMatch', ['throwWhenIterating', $this->createGeneratorThrowing(new \RuntimeException()), ['\\LogicException']])
        ;
    }

    function it_negative_match_nothing_thrown_when_nothing_should_be_thrown()
    {
        $this
            ->shouldNotThrow(FailureException::class)
            ->during('negativeMatch', ['throwWhenIterating', $this->createGeneratorThrowing(null), []])
        ;
    }

    function it_does_not_negative_match_something_thrown_when_nothing_should_be_thrown()
    {
        $this
            ->shouldThrow(FailureException::class)
            ->during('negativeMatch', ['throwWhenIterating', $this->createGeneratorThrowing(new \Exception()), []])
        ;
    }

    function it_negative_match_something_thrown_different_than_expected()
    {
        $this
            ->shouldNotThrow(FailureException::class)
            ->during('negativeMatch', ['throwWhenIterating', $this->createGeneratorThrowing(new \RuntimeException()), ['\\LogicException']])
        ;
    }

    function it_does_not_negative_match_something_thrown_same_as_expected()
    {
        $this
            ->shouldThrow(FailureException::class)
            ->during('negativeMatch', ['throwWhenIterating', $this->createGeneratorThrowing(new \RuntimeException()), ['\\RuntimeException']])
        ;
    }
    
    /**
     * @param array $values
     *
     * @return \Generator
     */
    private function createGeneratorThrowing(\Exception $exception = null) {
        yield 1;
        yield 2;
        if (null !== $exception) {
            throw $exception;
        }
        yield 3;
    }
}
