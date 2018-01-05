<?php

namespace spec\PhpSpec\Matcher;

use PhpSpec\Exception\Example\FailureException;
use PhpSpec\Formatter\Presenter\Presenter;
use PhpSpec\Matcher\Matcher;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class FloatValueMatcherSpec extends ObjectBehavior
{
    function let(Presenter $presenter)
    {
        $this->beConstructedWith($presenter);
    }

    function it_is_a_matcher()
    {
        $this->shouldBeAnInstanceOf(Matcher::class);
    }

    function it_responds_to_return()
    {
        $this->supports('return', 1.2, [1.2])->shouldReturn(true);
    }

    function it_responds_to_be()
    {
        $this->supports('be', 1.2, [1.2])->shouldReturn(true);
    }

    function it_responds_to_equal()
    {
        $this->supports('equal', 1.2, [1.2])->shouldReturn(true);
    }

    function it_responds_to_beEqualTo()
    {
        $this->supports('beEqualTo', 1.2, [1.2])->shouldReturn(true);
    }

    function it_matches_float_values()
    {
        $this->shouldNotThrow()->duringPositiveMatch('be', 1.2, [1.2]);
    }

    function it_matches_subtracted_float_values()
    {
        $this->shouldNotThrow()->duringPositiveMatch('be', (1.2 - 1), [0.1 + 0.1]);
    }

    function it_matches_multiplied_float_values()
    {
        $this->shouldNotThrow()->duringPositiveMatch('be', (2.3 * 1.5), [3.45]);
    }

    function it_does_not_match_different_float_values(Presenter $presenter)
    {
        $presenter->presentValue(Argument::any())->willReturn('0.00010000000000002', '0.00010000000000001');

        $this->shouldThrow(new FailureException('Expected 0.00010000000000002, but got 0.00010000000000001.'))
            ->duringPositiveMatch('be', 0.00010000000000001, [0.00010000000000002]);
    }
}
