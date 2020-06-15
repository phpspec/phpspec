<?php


namespace spec\PhpSpec\Matcher;


use PhpSpec\Exception\Example\FailureException;
use PhpSpec\Exception\Example\SkippingException;
use PhpSpec\Formatter\Presenter\Presenter;
use PhpSpec\Matcher\Matcher;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class ScalarMatcherSpec extends ObjectBehavior
{
    function let(Presenter $presenter)
    {
        $presenter->presentValue(Argument::any())->willReturn('val1', 'val2');
        $presenter->presentString(Argument::any())->willReturn('str');

        $this->beConstructedWith($presenter);
    }

    function it_is_a_matcher()
    {
        $this->shouldBeAnInstanceOf(Matcher::class);
    }

    function it_responds_to_be_array()
    {
        $this->supports('beArray', '', [''])->shouldReturn(true);
    }

    function it_matches_array()
    {
        $this->shouldNotThrow()->duringPositiveMatch('beArray', [], ['']);
    }

    function it_does_not_match_not_array_with_be_array_matcher()
    {
        $this->shouldThrow(FailureException::class)->duringPositiveMatch('beArray', Argument::not([]), ['']);
    }

    function it_mismatches_not_array()
    {
        $this->shouldNotThrow()->duringNegativeMatch('beArray', Argument::not([]), ['']);
    }

    function it_does_not_mismatch_array()
    {
        $this->shouldThrow(FailureException::class)->duringNegativeMatch('beArray', [], ['']);
    }

    function it_responds_to_be_bool()
    {
        $this->supports('beBool', '', [''])->shouldReturn(true);
    }

    function it_matches_bool()
    {
        $this->shouldNotThrow()->duringPositiveMatch('beBool', false, ['']);
    }

    function it_does_not_match_not_bool_with_be_bool_matcher()
    {
        $this->shouldThrow(FailureException::class)->duringPositiveMatch('beBool', Argument::not(false), ['']);
    }

    function it_mismatches_not_bool()
    {
        $this->shouldNotThrow()->duringNegativeMatch('beBool', Argument::not(false), ['']);
    }

    function it_does_not_mismatch_bool()
    {
        $this->shouldThrow(FailureException::class)->duringNegativeMatch('beBool', false, ['']);
    }

    function it_responds_to_be_boolean()
    {
        $this->supports('beBoolean', '', [''])->shouldReturn(true);
    }

    function it_matches_boolean()
    {
        $this->shouldNotThrow()->duringPositiveMatch('beBoolean', false, ['']);
    }

    function it_does_not_match_not_boolean()
    {
        $this->shouldThrow(FailureException::class)->duringPositiveMatch('beBoolean', Argument::not(false), ['']);
    }

    function it_mismatches_not_boolean()
    {
        $this->shouldNotThrow()->duringNegativeMatch('beBoolean', Argument::not(false), ['']);
    }

    function it_does_not_mismatch_boolean()
    {
        $this->shouldThrow(FailureException::class)->duringNegativeMatch('beBoolean', false, ['']);
    }

    function it_responds_to_be_callable()
    {
        $this->supports('beCallable', '', [''])->shouldReturn(true);
    }

    function it_matches_callable()
    {
        $this->shouldNotThrow()->duringPositiveMatch('beCallable', function () { return true; }, ['']);
    }

    function it_does_not_match_not_callable()
    {
        $this->shouldThrow(FailureException::class)->duringPositiveMatch('beCallable', Argument::not(function () { return true; }), ['']);
    }

    function it_mismatches_not_callable()
    {
        $this->shouldNotThrow()->duringNegativeMatch('beCallable', Argument::not(function () { return true; }), ['']);
    }

    function it_does_not_mismatch_callable()
    {
        $this->shouldThrow(FailureException::class)->duringNegativeMatch('beCallable', function () { return true; }, ['']);
    }

//    FROM PHP 7.3 - Implement also positive match and negative match
//    function it_responds_to_be_countable()
//    {
//        $this->supports('beCountable', '', [''])->shouldReturn(true);
//    }

    function it_responds_to_be_double()
    {
        $this->supports('beDouble', '', [''])->shouldReturn(true);
    }

    function it_matches_double()
    {
        $this->shouldNotThrow()->duringPositiveMatch('beDouble', doubleval(10.5), ['']);
    }

    function it_does_not_match_not_double()
    {
        $this->shouldThrow(FailureException::class)->duringPositiveMatch('beDouble', Argument::not(doubleval(10.5)), ['']);
    }

    function it_mismatches_not_double()
    {
        $this->shouldNotThrow()->duringNegativeMatch('beDouble', Argument::not(doubleval(10.5)), ['']);
    }

    function it_does_not_mismatches_double()
    {
        $this->shouldThrow(FailureException::class)->duringNegativeMatch('beDouble', doubleval(10.5), ['']);
    }

    function it_responds_to_be_float()
    {
        $this->supports('beFloat', '', [''])->shouldReturn(true);
    }

    function it_matches_float()
    {
        $this->shouldNotThrow()->duringPositiveMatch('beFloat', 10.5, ['']);
    }

    function it_does_not_match_not_float()
    {
        $this->shouldThrow(FailureException::class)->duringPositiveMatch('beFloat', Argument::not(10.5), ['']);
    }

    function it_mismatches_not_float()
    {
        $this->shouldNotThrow()->duringNegativeMatch('beFloat', Argument::not(10.5), ['']);
    }

    function it_does_not_mismatches_float()
    {
        $this->shouldThrow(FailureException::class)->duringNegativeMatch('beFloat', 10.5, ['']);
    }

    function it_responds_to_be_int()
    {
        $this->supports('beInt', '', [''])->shouldReturn(true);
    }

    function it_matches_int()
    {
        $this->shouldNotThrow()->duringPositiveMatch('beInt', 1, ['']);
    }

    function it_does_not_match_not_int()
    {
        $this->shouldThrow(FailureException::class)->duringPositiveMatch('beInt', Argument::not(1), ['']);
    }

    function it_mismatches_not_int()
    {
        $this->shouldNotThrow()->duringNegativeMatch('beInt', Argument::not(1), ['']);
    }

    function it_does_not_mismatches_int()
    {
        $this->shouldThrow(FailureException::class)->duringNegativeMatch('beInt', 1, ['']);
    }

    function it_responds_to_be_integer()
    {
        $this->supports('beInteger', '', [''])->shouldReturn(true);
    }

    function it_matches_int_with_integer_matcher()
    {
        $this->shouldNotThrow()->duringPositiveMatch('beInteger', 1, ['']);
    }

    function it_does_not_match_not_integer_match()
    {
        $this->shouldThrow(FailureException::class)->duringPositiveMatch('beInteger', Argument::not(1), ['']);
    }

    function it_mismatches_not_integer()
    {
        $this->shouldNotThrow()->duringNegativeMatch('beInteger', Argument::not(1), ['']);
    }

    function it_does_not_mismatches_integer()
    {
        $this->shouldThrow(FailureException::class)->duringNegativeMatch('beInteger', 1, ['']);
    }

    function it_responds_to_be_iterable()
    {
        $this->supports('beIterable', '', [''])->shouldReturn(true);
    }

    function it_matches_iterable()
    {
        $this->shouldNotThrow()->duringPositiveMatch('beIterable', [], ['']);
    }

    function it_does_not_match_not_iterable()
    {
        $this->shouldThrow(FailureException::class)->duringPositiveMatch('beIterable', Argument::not([]), ['']);
    }

    function it_mismatches_not_iterable()
    {
        $this->shouldNotThrow()->duringNegativeMatch('beIterable', Argument::not([]), ['']);
    }

    function it_does_not_mismatches_iterable()
    {
        $this->shouldThrow(FailureException::class)->duringNegativeMatch('beIterable', [], ['']);
    }

    function it_responds_to_be_long()
    {
        $this->supports('beLong', '', [''])->shouldReturn(true);
    }

    function it_matches_long()
    {
        $this->shouldNotThrow()->duringPositiveMatch('beLong', PHP_INT_MAX, ['']);
    }

    function it_does_not_match_not_long()
    {
        $this->shouldThrow(FailureException::class)->duringPositiveMatch('beLong', Argument::not(PHP_INT_MAX), ['']);
    }

    function it_mismatches_not_long()
    {
        $this->shouldNotThrow()->duringNegativeMatch('beLong', Argument::not(PHP_INT_MAX), ['']);
    }

    function it_does_not_mismatches_long()
    {
        $this->shouldThrow(FailureException::class)->duringNegativeMatch('beLong', PHP_INT_MAX, ['']);
    }

    function it_responds_to_be_null()
    {
        $this->supports('beNull', '', [''])->shouldReturn(true);
    }

    function it_matches_null()
    {
        $this->shouldNotThrow()->duringPositiveMatch('beNull', null, ['']);
    }

    function it_does_not_match_not_null()
    {
        $this->shouldThrow(FailureException::class)->duringPositiveMatch('beNull', Argument::not(null), ['']);
    }

    function it_mismatches_not_null()
    {
        $this->shouldNotThrow()->duringNegativeMatch('beNull', Argument::not(null), ['']);
    }

    function it_does_not_mismatches_null()
    {
        $this->shouldThrow(FailureException::class)->duringNegativeMatch('beNull', null, ['']);
    }

    function it_responds_to_be_numeric()
    {
        $this->supports('beNumeric', '', [''])->shouldReturn(true);
    }

    function it_matches_numeric_string()
    {
        $this->shouldNotThrow()->duringPositiveMatch('beNumeric', '123', ['']);
    }

    function it_matches_numeric_number()
    {
        $this->shouldNotThrow()->duringPositiveMatch('beNumeric', 123, ['']);
    }

    function it_does_not_match_not_numeric_string()
    {
        $this->shouldThrow(FailureException::class)->duringPositiveMatch('beNumeric', Argument::not('123'), ['']);
    }

    function it_does_not_match_not_numeric()
    {
        $this->shouldThrow(FailureException::class)->duringPositiveMatch('beNumeric', Argument::not(123), ['']);
    }

    function it_mismatches_not_number()
    {
        $this->shouldNotThrow()->duringNegativeMatch('beNumeric', Argument::not(123), ['']);
    }

    function it_does_not_mismatches_number()
    {
        $this->shouldThrow(FailureException::class)->duringNegativeMatch('beNumeric', 123, ['']);
    }

    function it_responds_to_be_object()
    {
        $this->supports('beObject', '', [''])->shouldReturn(true);
    }

    function it_matches_object()
    {
        $this->shouldNotThrow()->duringPositiveMatch('beObject', new \stdClass(), ['']);
    }

    function it_does_not_match_not_object()
    {
        $this->shouldThrow(FailureException::class)->duringPositiveMatch('beObject', null, ['']);
    }

    function it_mismatches_not_object()
    {
        $this->shouldNotThrow()->duringNegativeMatch('beObject', null, ['']);
    }

    function it_does_not_mismatches_object()
    {
        $this->shouldThrow(FailureException::class)->duringNegativeMatch('beObject', new \stdClass(), ['']);
    }

    function it_responds_to_be_resource()
    {
        $this->supports('beResource', '', [''])->shouldReturn(true);
    }

    function it_matches_a_resource()
    {
        $fp = fopen(__FILE__, 'r');
        $this->shouldNotThrow()->duringPositiveMatch('beResource', $fp, ['']);
        fclose($fp);
    }

    function it_does_not_match_not_resource()
    {
        $this->shouldThrow(FailureException::class)->duringPositiveMatch('beResource', null, ['']);
    }

    function it_mismatches_not_resource()
    {
        $this->shouldNotThrow()->duringNegativeMatch('beResource', null, ['']);
    }

    function it_does_not_mismatches_resource()
    {
        $fp = fopen(__FILE__, 'r');
        $this->shouldThrow(FailureException::class)->duringNegativeMatch('beResource', $fp, ['']);
        fclose($fp);
    }

    function it_responds_to_be_scalar()
    {
        $this->supports('beScalar', '', [''])->shouldReturn(true);
    }

    function it_matches_scalar()
    {
        $this->shouldNotThrow()->duringPositiveMatch('beScalar', 'foo', ['']);
    }

    function it_does_not_match_not_scalar()
    {
        $this->shouldThrow(FailureException::class)->duringPositiveMatch('beResource', null, ['']);
    }

    function it_mismatches_not_scalar()
    {
        $this->shouldNotThrow()->duringNegativeMatch('beResource', null, ['']);
    }

    function it_does_not_mismatches_scalar()
    {
        $this->shouldThrow(FailureException::class)->duringNegativeMatch('beScalar', 'foo', ['']);
    }

    function it_responds_to_be_string()
    {
        $this->supports('beString', '', [''])->shouldReturn(true);
    }

    function it_matches_string()
    {
        $this->shouldNotThrow()->duringPositiveMatch('beString', 'foo', ['']);
    }

    function it_does_not_match_not_string()
    {
        $this->shouldThrow(FailureException::class)->duringPositiveMatch('beString', Argument::not('foo'), ['']);
    }

    function it_mismatches_not_stringt()
    {
        $this->shouldNotThrow()->duringNegativeMatch('beString', Argument::not('foo'), ['']);
    }

    function it_does_not_mismatches_string()
    {
        $this->shouldThrow(FailureException::class)->duringNegativeMatch('beString', 'foo', ['']);
    }
}
