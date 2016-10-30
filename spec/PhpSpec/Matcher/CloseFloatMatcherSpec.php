<?php

namespace spec\PhpSpec\Matcher;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use PhpSpec\Formatter\Presenter\Presenter;

class CloseFloatMatcherSpec extends ObjectBehavior
{
    function let(Presenter $presenter)
    {
        $presenter->presentValue(Argument::any())->willReturn(41.889240346184, 1);
        $this->beConstructedWith($presenter);
    }

    function it_is_a_matcher()
    {
        $this->shouldBeAnInstanceOf('PhpSpec\Matcher\Matcher');
    }

    function it_matches_same_float()
    {
        $this->shouldNotThrow()->duringPositiveMatch('shouldBeACloseFloat', 1.4444444444, array(1.4444444444, 9));
    }

    function it_does_not_match_different_floats()
    {
        $this->shouldThrow()->duringPositiveMatch('shouldBeACloseFloat', 1.4444444444, array(1.444447777, 9));
    }

    function it_match_floats_with_near_float()
    {
        $this->shouldNotThrow()->duringPositiveMatch('shouldBeACloseFloat', 1.4455, array(1.4466, 2));
    }

}
