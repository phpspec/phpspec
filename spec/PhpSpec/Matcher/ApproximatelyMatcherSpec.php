<?php

namespace spec\PhpSpec\Matcher;

use PhpSpec\Exception\Example\NotEqualException;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use PhpSpec\Formatter\Presenter\Presenter;

class ApproximatelyMatcherSpec extends ObjectBehavior
{
    function let(Presenter $presenter)
    {
        $presenter->presentValue(Argument::any())->willReturn(41.889240346184,  1.0e-1);
        $this->beConstructedWith($presenter);
    }

    function it_is_a_matcher()
    {
        $this->shouldBeAnInstanceOf('PhpSpec\Matcher\Matcher');
    }

    function it_supports_various_aliases()
    {
        $this->supports('beApproximately', 1.0, [1.0, 5])->shouldReturn(true);
        $this->supports('beEqualToApproximately', 1.0, [1.0, 5])->shouldReturn(true);
        $this->supports('equalApproximately', 1.0, [1.0, 5])->shouldReturn(true);
        $this->supports('returnApproximately', 1.0, [1.0, 5])->shouldReturn(true);
    }

    function it_supports_valid_pairings()
    {
        $this->supports('beApproximately', 1.0, [1.0, 5])->shouldReturn(true);
        $this->supports('beApproximately', 1, [1.0, 5])->shouldReturn(true);
        $this->supports('beApproximately', '1', [1.0, 5])->shouldReturn(true);
        $this->supports('beApproximately', 1.0, [1, 5])->shouldReturn(true);
        $this->supports('beApproximately', 1.0, ['1', 5])->shouldReturn(true);
    }

    function it_does_not_support_comparing_objects()
    {
        $this->supports('beApproximately', 1.0, [new \stdClass, 5])->shouldReturn(false);
        $this->supports('beApproximately', new \stdClass, [1.0, 5])->shouldReturn(false);
        $this->supports('beApproximately', new \stdClass, [new \stdClass, 5])->shouldReturn(false);
    }

    function it_does_not_support_comparing_arrays_to_scalar()
    {
        $this->supports('beApproximately', 1.0, [[], 5])->shouldReturn(false);
        $this->supports('beApproximately', [], [1.0, 5])->shouldReturn(false);
    }

    function it_matches_same_float()
    {
        $this->shouldNotThrow()->duringPositiveMatch('shouldBeApproximately', 1.4444444444, array(1.4444444445,  1.0e-9));
    }

    function it_does_not_match_different_floats()
    {
        $this->shouldThrow()->duringPositiveMatch('shouldBeApproximately', 1.4444444444, array(1.444447777,  1.0e-9));
    }

    function it_match_floats_with_near_float()
    {
        $this->shouldNotThrow()->duringPositiveMatch('shouldBeApproximately', 1.4455, array(1.4466,  1.0e-2));
    }

    function it_matches_int_with_same_ints()
    {
        $this->shouldNotThrow()->duringPositiveMatch('shouldBeApproximately', 2, [2, 1.0e-1]);
    }

    function it_matches_string_with_same_string()
    {
        $this->shouldNotThrow()->duringPositiveMatch('shouldBeApproximately', '2.0', ['2.0', 1.0e-1]);
    }

    function it_matches_arrays_with_same_values()
    {
        $this->shouldNotThrow()->duringPositiveMatch('shouldBeApproximately', ['3.142', '2.0'], [['3.1','2.0'], 1.0e-1]);
    }

    function it_does_not_match_arrays_with_wrong_values()
    {
        $this->shouldThrow(NotEqualException::class)->duringPositiveMatch('shouldBeApproximately', ['3.142', '2.0'], [['3.3','2.0'], 1.0e-1]);
    }

    function it_does_not_match_arrays_with_different_lengths()
    {
        $this->shouldThrow(NotEqualException::class)->duringPositiveMatch('shouldBeApproximately', ['3.142', '2.0', '5.0'], [['3.3','2.0'], 1.0e-1]);
    }
}
