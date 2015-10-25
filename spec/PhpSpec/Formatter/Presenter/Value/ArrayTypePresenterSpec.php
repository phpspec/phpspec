<?php

namespace spec\PhpSpec\Formatter\Presenter\Value;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class ArrayTypePresenterSpec extends ObjectBehavior
{
    function it_is_a_type_presenter()
    {
        $this->shouldImplement('PhpSpec\Formatter\Presenter\Value\TypePresenter');
    }

    function it_should_support_array_values()
    {
        $this->supports(array())->shouldReturn(true);
    }

    function it_should_present_an_empty_array_as_a_string()
    {
        $this->present(array())->shouldReturn('[array:0]');
    }

    function it_should_present_a_populated_array_as_a_string()
    {
        $this->present(array('a', 'b'))->shouldReturn('[array:2]');
    }
}
