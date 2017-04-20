<?php

namespace spec\PhpSpec\Matcher;

use PhpSpec\Exception\Example\FailureException;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

use PhpSpec\Formatter\Presenter\Presenter;

class ArrayContainMatcherSpec extends ObjectBehavior
{
    function let(Presenter $presenter)
    {
        $presenter->presentValue(Argument::any())->willReturn('countable');
        $presenter->presentString(Argument::any())->willReturnArgument();

        $this->beConstructedWith($presenter);
    }

    function it_is_a_matcher()
    {
        $this->shouldBeAnInstanceOf('PhpSpec\Matcher\Matcher');
    }

    function it_supports_arrays()
    {
        $this->supports('contain', array(), array(''))->shouldReturn(true);
    }

    function it_supports_array_objects()
    {
        $this->supports('contain', new \ArrayObject(), [''])->shouldReturn(true);
    }

    function it_matches_array_with_specified_value()
    {
        $this->shouldNotThrow()->duringPositiveMatch('contain', array('abc'), array('abc'));
    }

    function it_does_not_match_array_without_specified_value()
    {
        $this->shouldThrow()->duringPositiveMatch('contain', array(1,2,3), array('abc'));
        $this->shouldThrow('PhpSpec\Exception\Example\FailureException')
            ->duringPositiveMatch('contain', array(1,2,3), array(new \stdClass()));
    }

    function it_matches_array_without_specified_value()
    {
        $this->shouldNotThrow()->duringNegativeMatch('contain', array(1,2,3), array('abc'));
    }

    function it_matches_array_object_with_specified_value()
    {
        $this->shouldNotThrow()->duringPositiveMatch('contain', new \ArrayObject([1, 2, 3]), array(1));
    }

    function it_does_not_match_array_object_without_specified_value()
    {
        $this->shouldThrow()->duringPositiveMatch('contain', new \ArrayObject([1, 2, 3]), ['abc']);
        $this->shouldThrow(FailureException::class)
            ->duringPositiveMatch('contain', new \ArrayObject([1, 2, 3]), [new \stdClass()]);
    }
}
