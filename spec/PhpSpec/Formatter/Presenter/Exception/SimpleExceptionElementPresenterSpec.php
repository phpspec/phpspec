<?php

namespace spec\PhpSpec\Formatter\Presenter\Exception;

use PhpSpec\Formatter\Presenter\Value\ExceptionTypePresenter;
use PhpSpec\Formatter\Presenter\Value\ValuePresenter;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class SimpleExceptionElementPresenterSpec extends ObjectBehavior
{
    function let(ExceptionTypePresenter $typePresenter, ValuePresenter $valuePresenter)
    {
        $this->beConstructedWith($typePresenter, $valuePresenter);
    }

    function it_is_an_exception_element_presenter()
    {
        $this->shouldImplement('PhpSpec\Formatter\Presenter\Exception\ExceptionElementPresenter');
    }

    function it_should_return_a_simple_exception_thrown_message(
        ExceptionTypePresenter $typePresenter, \Exception $exception
    ) {
        $typePresenter->present($exception)->willReturn('exc');
        $this->presentExceptionThrownMessage($exception)->shouldReturn('Exception exc has been thrown.');
    }

    function it_should_present_a_code_line()
    {
        $this->presentCodeLine('3', '4')->shouldReturn('3 4');
    }

    function it_should_present_a_highlighted_line_unchanged()
    {
        $this->presentHighlight('foo')->shouldReturn('foo');
    }

    function it_should_present_the_header_of_an_exception_trace_unchanged()
    {
        $this->presentExceptionTraceHeader('foo')->shouldReturn('foo');
    }

    function it_should_present_every_argument_in_an_exception_trace_method_as_a_value(ValuePresenter $valuePresenter)
    {
        $args = array('foo', 42);
        $valuePresenter->presentValue('foo')->shouldBeCalled();
        $valuePresenter->presentValue(42)->shouldBeCalled();

        $this->presentExceptionTraceMethod('', '', '', $args);
    }

    function it_should_present_an_exception_trace_method(ValuePresenter $valuePresenter)
    {
        $valuePresenter->presentValue('a')->willReturn('zaz');
        $valuePresenter->presentValue('b')->willReturn('zbz');

        $this->presentExceptionTraceMethod('class', 'type', 'method', array('a', 'b'))
            ->shouldReturn('   classtypemethod(zaz, zbz)');
    }

    function it_should_present_every_argument_in_an_exception_trace_function_as_a_value(ValuePresenter $valuePresenter)
    {
        $args = array('foo', 42);
        $valuePresenter->presentValue('foo')->shouldBeCalled();
        $valuePresenter->presentValue(42)->shouldBeCalled();

        $this->presentExceptionTraceFunction('', $args);
    }

    function it_should_present_an_exception_trace_function(ValuePresenter $valuePresenter)
    {
        $valuePresenter->presentValue('a')->willReturn('zaz');
        $valuePresenter->presentValue('b')->willReturn('zbz');

        $this->presentExceptionTraceFunction('function', array('a', 'b'))
            ->shouldReturn('   function(zaz, zbz)');
    }
}
