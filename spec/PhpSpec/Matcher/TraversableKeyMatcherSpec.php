<?php

namespace spec\PhpSpec\Matcher;

use PhpSpec\Exception\Example\FailureException;
use PhpSpec\Formatter\Presenter\Presenter;
use PhpSpec\Matcher\Matcher;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

final class TraversableKeyMatcherSpec extends ObjectBehavior
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

    function it_responds_to_haveKey()
    {
        $this->supports('haveKey', $this->createGeneratorReturningKeys([]), [''])->shouldReturn(true);
    }

    function it_positive_matches_generator_with_specified_key()
    {
        $this
            ->shouldNotThrow()
            ->during('positiveMatch', ['haveKey', $this->createGeneratorReturningKeys(['abc', 'def']), ['def']])
        ;
    }

    function it_does_not_positive_match_generator_without_specified_key()
    {
        $this
            ->shouldThrow(FailureException::class)
            ->during('positiveMatch', ['haveKey', $this->createGeneratorReturningKeys(['def']), ['abc']])
        ;
    }

    function it_negative_matches_generator_without_specified_key()
    {
        $this
            ->shouldNotThrow()
            ->during('negativeMatch', ['haveKey', $this->createGeneratorReturningKeys(['abc']), ['def']])
        ;
    }

    function it_does_not_negative_matches_generator_with_specified_key()
    {
        $this
            ->shouldThrow(FailureException::class)
            ->during('negativeMatch', ['haveKey', $this->createGeneratorReturningKeys(['abc', 'def']), ['def']])
        ;
    }

    /**
     * @param array $keys
     *
     * @return \Generator
     */
    private function createGeneratorReturningKeys(array $keys) {
        foreach ($keys as $key) {
            yield $key => 'value';
        }
    }
}
