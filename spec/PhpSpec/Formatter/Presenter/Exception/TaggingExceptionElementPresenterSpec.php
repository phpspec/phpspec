<?php

namespace spec\PhpSpec\Formatter\Presenter\Exception;

use PhpSpec\Formatter\Presenter\Exception\ExceptionElementPresenter;
use PhpSpec\Formatter\Presenter\Value\ExceptionTypePresenter;
use PhpSpec\Formatter\Presenter\Value\ValuePresenter;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class TaggingExceptionElementPresenterSpec extends ObjectBehavior
{
    function let(ExceptionTypePresenter $exceptionTypePresenter, ValuePresenter $valuePresenter)
    {
        $this->beConstructedWith($exceptionTypePresenter, $valuePresenter);
    }

    function it_is_an_exception_element_presenter()
    {
        $this->shouldImplement('PhpSpec\Formatter\Presenter\Exception\ExceptionElementPresenter');
    }

    function it_should_tag_an_exception_thrown_message(
        ExceptionTypePresenter $exceptionTypePresenter,
        \Exception $exception
    ) {
        $exceptionTypePresenter->present($exception)->willReturn('exc');
        $this->presentExceptionThrownMessage($exception)->shouldReturn('Exception <label>exc</label> has been thrown.');
    }

    function it_should_present_a_tagged_code_line()
    {
        $this->presentCodeLine('3', 'foo')->shouldReturn('<lineno>3</lineno> <code>foo</code>');
    }

    function it_should_present_a_tagged_highlighted_line()
    {
        $this->presentHighlight('foo')->shouldReturn('<hl>foo</hl>');
    }

    function it_should_present_a_tagged_header_of_an_exception_trace()
    {
        $this->presentExceptionTraceHeader('foo')->shouldReturn('<trace>foo</trace>');
    }

    function it_should_present_a_tagged_exception_trace_method(ValuePresenter $valuePresenter)
    {
        $valuePresenter->presentValue('a')->willReturn('zaz');
        $valuePresenter->presentValue('b')->willReturn('zbz');

        $result = '   <trace><trace-class>class</trace-class><trace-type>type</trace-type>'.
            '<trace-func>method</trace-func>(<trace-args><value>zaz</value>, <value>zbz</value></trace-args>)</trace>';

        $this->presentExceptionTraceMethod('class', 'type', 'method', array('a', 'b'))->shouldReturn($result);
    }

    function it_should_present_a_tagged_exception_trace_function(ValuePresenter $valuePresenter)
    {
        $valuePresenter->presentValue('a')->willReturn('zaz');
        $valuePresenter->presentValue('b')->willReturn('zbz');

        $result = '   <trace><trace-func>function</trace-func>'.
            '(<trace-args><value>zaz</value>, <value>zbz</value></trace-args>)</trace>';

        $this->presentExceptionTraceFunction('function', array('a', 'b'))->shouldReturn($result);
    }
}
