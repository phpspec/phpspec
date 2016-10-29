<?php

namespace spec\PhpSpec\Matcher;

use PhpSpec\Exception\Example\FailureException;
use PhpSpec\Formatter\Presenter\Presenter;
use PhpSpec\Matcher\Matcher;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

final class TraversableKeyValueMatcherSpec extends ObjectBehavior
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

    function it_responds_to_haveKeyWithValue()
    {
        $this->supports('haveKeyWithValue', $this->createGeneratorReturningArray([]), ['', ''])->shouldReturn(true);
    }

    function it_positive_matches_generator_with_specified_key_and_value()
    {
        $this
            ->shouldNotThrow()
            ->during('positiveMatch', ['haveKeyWithValue', $this->createGeneratorReturningArray(['a' => 'b', 'c' => 'd']), ['a', 'b']])
        ;
    }

    function it_does_not_positive_match_generator_without_specified_key_and_value()
    {
        $this
            ->shouldThrow(FailureException::class)
            ->during('positiveMatch', ['haveKeyWithValue', $this->createGeneratorReturningArray(['a' => 'b', 'c' => 'd']), ['a', 'c']])
        ;

        $this
            ->shouldThrow(FailureException::class)
            ->during('positiveMatch', ['haveKeyWithValue', $this->createGeneratorReturningArray(['a' => 'b', 'c' => 'd']), ['b', 'd']])
        ;

        $this
            ->shouldThrow(FailureException::class)
            ->during('positiveMatch', ['haveKeyWithValue', $this->createGeneratorReturningArray(['a' => 'b', 'c' => 'd']), ['b', 'a']])
        ;
    }

    function it_negative_matches_generator_without_specified_key_and_value()
    {
        $this
            ->shouldNotThrow()
            ->during('negativeMatch', ['haveKeyWithValue', $this->createGeneratorReturningArray(['a' => 'b', 'c' => 'd']), ['a', 'c']])
        ;
    }

    function it_does_not_negative_matches_generator_with_specified_key_and_value()
    {
        $this
            ->shouldThrow(FailureException::class)
            ->during('negativeMatch', ['haveKeyWithValue', $this->createGeneratorReturningArray(['a' => 'b', 'c' => 'd']), ['a', 'b']])
        ;
    }

    /**
     * @param array $array
     *
     * @return \Generator
     */
    private function createGeneratorReturningArray(array $array) {
        foreach ($array as $key => $value) {
            yield $key => $value;
        }
    }
}
