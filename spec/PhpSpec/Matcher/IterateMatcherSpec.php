<?php

namespace spec\PhpSpec\Matcher;

use PhpSpec\Exception\Example\FailureException;
use PhpSpec\Formatter\Presenter\Presenter;
use PhpSpec\Matcher\Matcher;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

final class IterateMatcherSpec extends ObjectBehavior
{
    function let(Presenter $presenter)
    {
        $presenter->presentValue(Argument::any())->will(function ($subject) {
            return '"' . $subject[0] . '"';
        });

        $this->beConstructedWith($presenter);
    }

    function it_is_a_matcher()
    {
        $this->shouldBeAnInstanceOf(Matcher::class);
    }

    function it_responds_to_iterate()
    {
        $this->supports('iterate', [], [[]])->shouldReturn(true);

        $this->supports('iterate', new \ArrayObject([]), [[]])->shouldReturn(true);
        $this->supports('iterate', new \ArrayIterator([]), [[]])->shouldReturn(true);
        $this->supports('iterate', $this->createGeneratorReturningArray([]), [[]])->shouldReturn(true);

        $this->supports('iterate', [], [new \ArrayIterator([])])->shouldReturn(true);
        $this->supports('iterate', [], [new \ArrayObject([])])->shouldReturn(true);
        $this->supports('iterate', [], [$this->createGeneratorReturningArray([])])->shouldReturn(true);
    }

    function it_positive_matches_generator_while_iterating_the_same()
    {
        $this
            ->shouldNotThrow()
            ->during('positiveMatch', [
                'iterate',
                $this->createGeneratorReturningArray(['a' => 'b', 'c' => 'd']),
                [['a' => 'b', 'c' => 'd']],
            ])
        ;

        $this
            ->shouldNotThrow()
            ->during('positiveMatch', [
                'iterate',
                $this->createGeneratorReturningArray(['a' => 'b', 'c' => 'd']),
                [$this->createGeneratorReturningArray(['a' => 'b', 'c' => 'd'])],
            ])
        ;
    }

    function it_does_not_positive_match_generator_while_not_iterating_the_same()
    {
        $this
            ->shouldThrow(new FailureException('Expected subject to have element #1 with key "c" and value "e", but got key "c" and value "d".'))
            ->during('positiveMatch', [
                'iterate',
                $this->createGeneratorReturningArray(['a' => 'b', 'c' => 'd']),
                [['a' => 'b', 'c' => 'e']],
            ])
        ;

        $this
            ->shouldThrow(new FailureException('Expected subject to have element #1 with key "e" and value "d", but got key "c" and value "d".'))
            ->during('positiveMatch', [
                'iterate',
                $this->createGeneratorReturningArray(['a' => 'b', 'c' => 'd']),
                [$this->createGeneratorReturningArray(['a' => 'b', 'e' => 'd'])],
            ])
        ;

        $this
            ->shouldThrow(new FailureException('Expected subject to have the same number of elements than matched value, but it has less.'))
            ->during('positiveMatch', [
                'iterate',
                $this->createGeneratorReturningArray(['a' => 'b', 'c' => 'd']),
                [['a' => 'b', 'c' => 'd', 'e' => 'f']],
            ])
        ;

        $this
            ->shouldThrow(new FailureException('Expected subject to have the same number of elements than matched value, but it has more.'))
            ->during('positiveMatch', [
                'iterate',
                $this->createGeneratorReturningArray(['a' => 'b', 'c' => 'd']),
                [['a' => 'b']],
            ])
        ;
    }

    function it_negative_matches_generator_while_not_iterating_the_same()
    {
        $this
            ->shouldNotThrow()
            ->during('negativeMatch', [
                'iterate',
                $this->createGeneratorReturningArray(['a' => 'b', 'c' => 'd']),
                [['a' => 'b', 'c' => 'e']],
            ])
        ;

        $this
            ->shouldNotThrow()
            ->during('negativeMatch', [
                'iterate',
                $this->createGeneratorReturningArray(['a' => 'b', 'c' => 'd']),
                [$this->createGeneratorReturningArray(['a' => 'b', 'c' => 'e'])],
            ])
        ;
    }

    function it_does_not_negative_matches_generator_while_iterating_the_same()
    {
        $this
            ->shouldThrow(FailureException::class)
            ->during('negativeMatch', [
                'iterate',
                $this->createGeneratorReturningArray(['a' => 'b', 'c' => 'd']),
                [['a' => 'b', 'c' => 'd']],
            ])
        ;

        $this
            ->shouldThrow(FailureException::class)
            ->during('negativeMatch', [
                'iterate',
                $this->createGeneratorReturningArray(['a' => 'b', 'c' => 'd']),
                [$this->createGeneratorReturningArray(['a' => 'b', 'c' => 'd'])],
            ])
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
