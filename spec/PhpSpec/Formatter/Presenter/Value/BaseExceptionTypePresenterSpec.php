<?php

namespace spec\PhpSpec\Formatter\Presenter\Value;

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
}
