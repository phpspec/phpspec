<?php

namespace spec\PhpSpec\Matcher;

use PhpSpec\Exception\Example\FailureException;
use PhpSpec\Formatter\Presenter\Presenter;
use PhpSpec\Matcher\Matcher;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

final class TraversableCountMatcherSpec extends ObjectBehavior
{
    function let(Presenter $presenter)
    {
        $presenter->presentValue(Argument::any())->willReturn('traversable');
        $presenter->presentString(Argument::any())->willReturnArgument();

        $this->beConstructedWith($presenter);
    }

    function it_is_a_matcher()
    {
        $this->shouldBeAnInstanceOf(Matcher::class);
    }

    function it_responds_to_haveCount()
    {
        $this->supports('haveCount', $this->createGeneratorWithCount(0), [''])->shouldReturn(true);
    }

    function it_positive_matches_proper_generator_count()
    {
        $this
            ->shouldNotThrow()
            ->during('positiveMatch', ['haveCount', $this->createGeneratorWithCount(2), [2]])
        ;
    }

    function it_does_not_positive_match_infinite_generator()
    {
        $this
            ->shouldThrow(new FailureException('Expected traversable to have 10 items, but got more than that.'))
            ->during('positiveMatch', ['haveCount', $this->createInifiteGenerator(), [10]])
        ;
    }

    function it_does_not_positive_match_wrong_generator_count()
    {
        $this
            ->shouldThrow(new FailureException('Expected traversable to have 2 items, but got more than that.'))
            ->during('positiveMatch', ['haveCount', $this->createGeneratorWithCount(3), [2]])
        ;

        $this
            ->shouldThrow(new FailureException('Expected traversable to have 2 items, but got less than that.'))
            ->during('positiveMatch', ['haveCount', $this->createGeneratorWithCount(1), [2]])
        ;
    }

    function it_negative_matches_wrong_generator_count()
    {
        $this
            ->shouldNotThrow()
            ->during('negativeMatch', ['haveCount', $this->createGeneratorWithCount(3), [2]])
        ;
    }

    function it_negative_matches_infinite_generator()
    {
        $this
            ->shouldNotThrow()
            ->during('negativeMatch', ['haveCount', $this->createInifiteGenerator(), [10]])
        ;
    }

    function it_does_not_negative_match_proper_generator_count()
    {
        $this
            ->shouldThrow(new FailureException('Expected traversable not to have 2 items, but got it.'))
            ->during('negativeMatch', ['haveCount', $this->createGeneratorWithCount(2), [2]])
        ;
    }

    /**
     * @param int $count
     *
     * @return \Generator
     */
    private function createGeneratorWithCount($count)
    {
        for ($i = 0; $i < $count; ++$i) {
            yield $i;
        }
    }

    /**
     * @return \Generator
     */
    private function createInifiteGenerator()
    {
        while (true) {
            yield 42;
        }
    }
}
