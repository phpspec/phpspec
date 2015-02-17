<?php

namespace spec\PhpSpec\Matcher;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

use PhpSpec\Formatter\Presenter\PresenterInterface;

use ArrayObject;

class ArrayKeyValueMatcherSpec extends ObjectBehavior
{
    function let(PresenterInterface $presenter)
    {
        $presenter->presentValue(Argument::any())->willReturn('countable');
        $presenter->presentString(Argument::any())->willReturnArgument();

        $this->beConstructedWith($presenter);
    }

    function it_is_a_matcher()
    {
        $this->shouldBeAnInstanceOf('PhpSpec\Matcher\MatcherInterface');
    }

    function it_responds_to_haveKeyWithValue()
    {
        $this->supports('haveKeyWithValue', array(), array('', ''))->shouldReturn(true);
    }

    function it_matches_array_with_correct_value_for_specified_key()
    {
        $this->shouldNotThrow()->duringPositiveMatch('haveKeyWithValue', array('abc' => 123), array('abc', 123));
    }

    function it_matches_ArrayObject_with_correct_value_for_specified_offset(ArrayObject $array)
    {
        $array->offsetExists('abc')->willReturn(true);
        $array->offsetGet('abc')->willReturn(123);

        $this->shouldNotThrow()->duringPositiveMatch('haveKeyWithValue', $array, array('abc', 123));
    }

    function it_does_not_match_array_without_specified_key()
    {
        $this->shouldThrow()->duringPositiveMatch('haveKeyWithValue', array(1,2,3), array('abc', 123));
    }

    function it_does_not_match_ArrayObject_without_specified_offset(ArrayObject $array)
    {
        $array->offsetExists('abc')->willReturn(false);

        $this->shouldThrow()->duringPositiveMatch('haveKeyWithValue', $array, array('abc', 123));
    }

    function it_matches_array_without_specified_key()
    {
        $this->shouldNotThrow()->duringNegativeMatch('haveKeyWithValue', array(1,2,3), array('abc', 123));
    }

    function it_matches_ArrayObject_without_specified_offset(ArrayObject $array)
    {
        $array->offsetExists('abc')->willReturn(false);

        $this->shouldNotThrow()->duringNegativeMatch('haveKeyWithValue', $array, array('abc', 123));
    }
}
