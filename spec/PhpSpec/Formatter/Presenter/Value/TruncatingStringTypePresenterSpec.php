<?php

namespace spec\PhpSpec\Formatter\Presenter\Value;

use PhpSpec\Formatter\Presenter\Value\StringTypePresenter;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class TruncatingStringTypePresenterSpec extends ObjectBehavior
{
    function let(StringTypePresenter $stringTypePresenter)
    {
        $this->beConstructedWith($stringTypePresenter);
    }

    function it_is_a_string_type_presenter()
    {
        $this->shouldImplement('PhpSpec\Formatter\Presenter\Value\StringTypePresenter');
    }

    function it_should_support_string_values(StringTypePresenter $stringTypePresenter)
    {
        $stringTypePresenter->supports('foo')->willReturn(true);
        $this->supports('foo')->shouldReturn(true);
    }

    function it_should_pass_short_values_directly_to_the_decorated_string_type_presenter(
        StringTypePresenter $stringTypePresenter
    ) {
        $stringTypePresenter->present('foo')->willReturn('zfooz');
        $this->present('foo')->shouldReturn('zfooz');
    }

    function it_should_return_long_values_truncated(
        StringTypePresenter $stringTypePresenter
    ) {
        $stringTypePresenter->present('some_string_longer_than_t...')
            ->willReturn('some_string_longer_than_t...');
        $this->present('some_string_longer_than_twenty_five_chars')->shouldReturn('some_string_longer_than_t...');
    }

    function it_presents_only_first_line_of_multiline_string(StringTypePresenter $stringTypePresenter)
    {
        $stringTypePresenter->present('some...')->willReturn('some...');
        $this->present("some\nmultiline\nvalue")->shouldReturn('some...');
    }
}
