<?php

namespace spec\PhpSpec\Matcher;

use PhpSpec\Formatter\Presenter\Presenter;
use PhpSpec\Matcher\Iterate\SubjectElementDoesNotMatchException;
use PhpSpec\Matcher\Matcher;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

final class IterateLikeMatcherSpec extends ObjectBehavior
{
    function let(Presenter $presenter)
    {
        $presenter->presentValue(Argument::any())->will(function ($subject) {
            return '"' . var_export($subject[0], true) . '"';
        });

        $this->beConstructedWith($presenter);
    }

    function it_is_a_matcher()
    {
        $this->shouldBeAnInstanceOf(Matcher::class);
    }

    function it_responds_to_iterate_like()
    {
        $this->supports('iterateLike', [], [[]])->shouldReturn(true);

        $this->supports('iterateLike', new \ArrayObject([]), [[]])->shouldReturn(true);
        $this->supports('iterateLike', new \ArrayIterator([]), [[]])->shouldReturn(true);
        $this->supports('iterateLike', $this->createGeneratorReturningArray([]), [[]])->shouldReturn(true);

        $this->supports('iterateLike', [], [new \ArrayIterator([])])->shouldReturn(true);
        $this->supports('iterateLike', [], [new \ArrayObject([])])->shouldReturn(true);
        $this->supports('iterateLike', [], [$this->createGeneratorReturningArray([])])->shouldReturn(true);
    }

    function it_positive_matches_generator_while_iterating_likes()
    {
        $this
            ->shouldNotThrow()
            ->during('positiveMatch', [
                'iterateLike',
                $this->createGeneratorReturningArray([new \stdClass()]),
                [[new \stdClass()]],
            ])
        ;

        $this
            ->shouldNotThrow()
            ->during('positiveMatch', [
                'iterateLike',
                $this->createGeneratorReturningArray([new \stdClass()]),
                [$this->createGeneratorReturningArray([new \stdClass()])],
            ])
        ;
    }

    function it_does_not_positive_match_generator_while_not_iterating_the_same()
    {
        $first = new \stdClass();
        $first->foo = 'foo';

        $second = new \stdClass();
        $second->foo = 'bar';

        $this
            ->shouldThrow(SubjectElementDoesNotMatchException::class)
            ->during('positiveMatch', [
                'iterateLike',
                $this->createGeneratorReturningArray([$first]),
                [$this->createGeneratorReturningArray([$second])],
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
