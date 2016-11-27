<?php

namespace spec\PhpSpec\Matcher;

use PhpSpec\Exception\Example\FailureException;
use PhpSpec\Formatter\Presenter\Presenter;
use PhpSpec\Matcher\Iterate\SubjectElementDoesNotMatchException;
use PhpSpec\Matcher\Matcher;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

final class StartIteratingAsMatcherSpec extends ObjectBehavior
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

    function it_responds_to_startIterating()
    {
        $this->supports('startIteratingAs', [], [[]])->shouldReturn(true);

        $this->supports('startIteratingAs', new \ArrayObject([]), [[]])->shouldReturn(true);
        $this->supports('startIteratingAs', new \ArrayIterator([]), [[]])->shouldReturn(true);
        $this->supports('startIteratingAs', $this->createGeneratorReturningArray([]), [[]])->shouldReturn(true);

        $this->supports('startIteratingAs', [], [new \ArrayIterator([])])->shouldReturn(true);
        $this->supports('startIteratingAs', [], [new \ArrayObject([])])->shouldReturn(true);
        $this->supports('startIteratingAs', [], [$this->createGeneratorReturningArray([])])->shouldReturn(true);

        $this->supports('startYielding', [], [[]])->shouldReturn(true);
    }

    function it_positive_matches_generator_while_starting_iterating_the_same()
    {
        $this
            ->shouldNotThrow()
            ->during('positiveMatch', [
                'startIteratingAs',
                $this->createGeneratorReturningArray(['a' => 'b', 'c' => 'd']),
                [['a' => 'b']],
            ])
        ;

        $this
            ->shouldNotThrow()
            ->during('positiveMatch', [
                'startIteratingAs',
                $this->createGeneratorReturningArray(['a' => 'b', 'c' => 'd']),
                [$this->createGeneratorReturningArray(['a' => 'b'])],
            ])
        ;
    }

    function it_positive_matches_infinite_generator_while_starting_iterating_the_same()
    {
        $this
            ->shouldNotThrow()
            ->during('positiveMatch', [
                'startIteratingAs',
                $this->createInfiniteGenerator(),
                [[0 => 0, 1 => 1]]
            ])
        ;
    }

    function it_does_not_positive_match_generator_while_not_starting_iterating_the_same()
    {
        $this
            ->shouldThrow(new SubjectElementDoesNotMatchException(1, '"c"', '"d"', '"c"', '"e"'))
            ->during('positiveMatch', [
                'startIteratingAs',
                $this->createGeneratorReturningArray(['a' => 'b', 'c' => 'd']),
                [['a' => 'b', 'c' => 'e']],
            ])
        ;
    }

    function it_negative_matches_generator_while_not_starting_iterating_the_same()
    {
        $this
            ->shouldNotThrow()
            ->during('negativeMatch', [
                'startIteratingAs',
                $this->createGeneratorReturningArray(['a' => 'b', 'c' => 'd']),
                [['a' => 'b', 'c' => 'e']],
            ])
        ;

        $this
            ->shouldNotThrow()
            ->during('negativeMatch', [
                'startIteratingAs',
                $this->createGeneratorReturningArray(['a' => 'b', 'c' => 'd']),
                [$this->createGeneratorReturningArray(['a' => 'b', 'c' => 'e'])],
            ])
        ;
    }

    function it_negative_matches_infinite_generator_while_not_starting_iterating_the_same()
    {
        $this
            ->shouldNotThrow()
            ->during('negativeMatch', [
                'startIteratingAs',
                $this->createInfiniteGenerator(),
                [[0 => 0, 1 => 1, 3 => 3]],
            ])
        ;
    }

    function it_does_not_negative_matches_generator_while_starting_iterating_the_same()
    {
        $this
            ->shouldThrow(FailureException::class)
            ->during('negativeMatch', [
                'startIteratingAs',
                $this->createGeneratorReturningArray(['a' => 'b', 'c' => 'd']),
                [['a' => 'b']],
            ])
        ;

        $this
            ->shouldThrow(FailureException::class)
            ->during('negativeMatch', [
                'startIteratingAs',
                $this->createInfiniteGenerator(),
                [[0 => 0, 1 => 1]],
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

    /**
     * @return \Generator
     */
    private function createInfiniteGenerator() {
        for ($i = 0; true; ++$i) {
            yield $i => $i;
        }
    }
}
