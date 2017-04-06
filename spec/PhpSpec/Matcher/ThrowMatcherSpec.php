<?php

namespace spec\PhpSpec\Matcher;

use PhpSpec\Factory\ReflectionFactory;
use PhpSpec\ObjectBehavior;
use PhpSpec\Wrapper\Unwrapper;
use Prophecy\Argument;
use PhpSpec\Formatter\Presenter\Presenter;
use PhpSpec\Exception\Example\SkippingException;
use ArrayObject;

class ThrowMatcherSpec extends ObjectBehavior
{
    function let(Unwrapper $unwrapper, Presenter $presenter, ReflectionFactory $factory)
    {
        $unwrapper->unwrapAll(Argument::any())->willReturnArgument();
        $presenter->presentValue(Argument::any())->willReturn('val1', 'val2');

        $this->beConstructedWith($unwrapper, $presenter, $factory);
    }

    function it_supports_the_throw_alias_for_object_and_exception_name()
    {
        $this->supports('throw', '', array())->shouldReturn(true);
    }

    function it_accepts_a_method_during_which_an_exception_should_be_thrown(ArrayObject $arr)
    {
        $arr->ksort()->willThrow('\Exception');

        $this->positiveMatch('throw', $arr, array('\Exception'))->during('ksort', array());
    }

    function it_accepts_a_method_during_which_an_error_specified_by_class_name_should_be_thrown(ArrayObject $arr)
    {
        if (!class_exists('\Error')) {
            throw new SkippingException('The class Error, introduced in PHP 7, does not exist');
        }

        $arr->ksort()->willThrow('\Error');

        $this->positiveMatch('throw', $arr, array('\Error'))->during('ksort', array());
    }

    function it_accepts_a_method_during_which_an_error_specified_by_instance_should_be_thrown(ArrayObject $arr, ReflectionFactory $factory)
    {
        if (!class_exists('\Error')) {
            throw new SkippingException('The class Error, introduced in PHP 7, does not exist');
        }

        $error = new \Error();
        $arr->ksort()->will(function() use ($error) { throw $error; });
        $factory->create(Argument::any())->willReturn(new \ReflectionClass($error));

        $this->positiveMatch('throw', $arr, array(new \Error()))->during('ksort', array());
    }

    function it_accepts_a_method_during_which_an_exception_should_not_be_thrown(ArrayObject $arr)
    {
        $this->negativeMatch('throw', $arr, array('\Exception'))->during('ksort', array());
    }

    function it_accepts_a_method_during_which_an_error_should_not_be_thrown(ArrayObject $arr)
    {
        if (!class_exists('\Error')) {
            throw new SkippingException('The class Error, introduced in PHP 7, does not exist');
        }

        $this->negativeMatch('throw', $arr, array('\Error'))->during('ksort', array());
    }

    function it_throws_a_failure_exception_with_the_thrown_exceptions_message_if_a_positive_match_failed(Presenter $presenter)
    {
        if (!class_exists('\Error')) {
            throw new SkippingException('The class Error, introduced in PHP 7, does not exist');
        }

        $actually_thrown_error = new \Error('This is a test Error');

        $callable = function () use ($actually_thrown_error) { throw $actually_thrown_error; };

        $expected_error = new \PhpSpec\Exception\Example\FailureException(
            'Expected exception of class Exception, but got Error with the message: "This is a test Error"'
        );

        $incorrectly_matched_exception = new \Exception('This is the exception I expect to be thrown.');

        $presenter->presentValue($actually_thrown_error)->willReturn('Error');
        $presenter->presentValue($incorrectly_matched_exception)->willReturn('Exception');

        $this->shouldThrow($expected_error)->during('verifyPositive', [$callable, [], $incorrectly_matched_exception]);
    }
}
