<?php

namespace spec\PhpSpec\Formatter\Presenter\Value;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class QuotingStringTypePresenterSpec extends ObjectBehavior
{
    function it_is_a_string_type_presenter()
    {
        $this->shouldImplement('PhpSpec\Formatter\Presenter\Value\StringTypePresenter');
    }

    function it_should_support_string_values()
    {
        $this->supports('')->shouldReturn(true);
        $this->supports('foo')->shouldReturn(true);
    }

    function it_should_present_a_string_as_a_quoted_string()
    {
        $this->present('')->shouldReturn('""');
        $this->present('foo')->shouldReturn('"foo"');
    }
}
