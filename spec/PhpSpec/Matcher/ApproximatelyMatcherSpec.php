<?php

namespace spec\PhpSpec\Matcher;

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
}
