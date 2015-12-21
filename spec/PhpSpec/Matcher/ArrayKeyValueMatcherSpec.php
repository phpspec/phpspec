<?php

namespace spec\PhpSpec\Matcher;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

use PhpSpec\Formatter\Presenter\Presenter;

use PhpSpec\Exception\Example\FailureException;
use ArrayObject;

class ArrayKeyValueMatcherSpec extends ObjectBehavior
{
    function let(Presenter $presenter)
    {
        $presenter->presentValue(Argument::any())->will(function ($subject) {
            if (is_array($subject[0])) {
                return 'array';
            }
            if (is_object($subject[0])) {
                return 'object';
            }

            return $subject[0];
        });
        $presenter->presentString(Argument::any())->willReturnArgument();

        $this->beConstructedWith($presenter);
    }

    function it_is_a_matcher()
    {
        $this->shouldBeAnInstanceOf('PhpSpec\Matcher\Matcher');
    }

    function it_responds_to_haveKeyWithValue_with_array_subject()
    {
        $this->supports('haveKeyWithValue', array(), array('', ''))->shouldReturn(true);
    }

    function it_responds_to_haveKeyWithValue_with_array_access_subject()
    {
        $this->supports('haveKeyWithValue', new \ArrayIterator(), array('', ''))->shouldReturn(true);
    }

    function it_does_not_respond_to_haveKeyWithValue_with_non_array_subject()
    {
        $this->supports('haveKeyWithValue', null, array('', ''))->shouldReturn(false);
    }

    function it_matches_array_with_correct_value_for_specified_key()
    {
        $this->shouldNotThrow()->duringPositiveMatch('haveKeyWithValue', array('abc' => 123), array('abc', 123));
    }

    function it_does_not_match_array_with_wrong_value_for_specified_key()
    {
        $this->shouldThrow(new FailureException('Expected array to have value 456 for abc key, but found 123.'))->duringPositiveMatch('haveKeyWithValue', array('abc' => 123), array('abc', 456));
    }

    function it_does_not_match_array_with_missing_key()
    {
        $this->shouldThrow(new FailureException('Expected array to have key abc, but it didn\'t.'))->duringPositiveMatch('haveKeyWithValue', array(), array('abc', 123));
    }

    function it_matches_ArrayObject_with_correct_value_for_specified_offset(ArrayObject $array)
    {
        $array->offsetExists('abc')->willReturn(true);
        $array->offsetGet('abc')->willReturn(123);

        $this->shouldNotThrow()->duringPositiveMatch('haveKeyWithValue', $array, array('abc', 123));
    }

    function it_does_not_match_ArrayObject_with_missing_key(ArrayObject $array)
    {
        $this->shouldThrow(new FailureException('Expected object to have key abc, but it didn\'t.'))->duringPositiveMatch('haveKeyWithValue', $array, array('abc', 123));
    }

    function it_does_not_match_ArrayObject_with_wrong_value_for_specified_offset(ArrayObject $array)
    {
        $array->offsetExists('abc')->willReturn(true);
        $array->offsetGet('abc')->willReturn(123);

        $this->shouldThrow(new FailureException('Expected object to have value 456 for abc key, but found 123.'))->duringPositiveMatch('haveKeyWithValue', $array, array('abc', 456));
    }

    function it_matches_array_without_specified_key()
    {
        $this->shouldNotThrow()->duringNegativeMatch('haveKeyWithValue', array(1,2,3), array('abc', 123));
    }

    function it_matches_array_with_invalid_key_value()
    {
        $this->shouldNotThrow()->duringNegativeMatch('haveKeyWithValue', array('abc' => 456), array('abc', 123));
    }

    function it_matches_ArrayObject_without_specified_offset(ArrayObject $array)
    {
        $array->offsetExists('abc')->willReturn(false);

        $this->shouldNotThrow()->duringNegativeMatch('haveKeyWithValue', $array, array('abc', 123));
    }

    function it_matches_ArrayObject_with_invalid_key_value(ArrayObject $array)
    {
        $array->offsetExists('abc')->willReturn(true);
        $array->offsetGet('abc')->willReturn(456);

        $this->shouldNotThrow()->duringNegativeMatch('haveKeyWithValue', $array, array('abc', 123));
    }
}
