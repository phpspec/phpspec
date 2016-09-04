<?php

namespace spec\PhpSpec\Formatter\Presenter\Value;

use PhpSpec\Exception\ErrorException;
use PhpSpec\Exception\Example\SkippingException;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class BaseExceptionTypePresenterSpec extends ObjectBehavior
{
    function it_is_a_type_presenter()
    {
        $this->shouldImplement('PhpSpec\Formatter\Presenter\Value\ExceptionTypePresenter');
    }

    function it_should_support_exceptions()
    {
        $this->supports(new \Exception())->shouldReturn(true);
    }

    function it_should_present_an_exception_as_a_string()
    {
        $this->present(new \Exception('foo'))
            ->shouldReturn('[exc:Exception("foo")]');
    }

    function it_should_present_an_error_as_a_string()
    {
        if (!class_exists('\Error')) {
            throw new SkippingException('The class Error, introduced in PHP 7, does not exist');
        }

        $this->present(new ErrorException(new \Error('foo')))
            ->shouldReturn('[err:Error("foo")]');
    }
}
