<?php

namespace spec\PhpSpec\Formatter\Presenter;

use PhpSpec\Formatter\Presenter\Presenter;
use PhpSpec\ObjectBehavior;

class TaggingPresenterSpec extends ObjectBehavior
{
    function let(Presenter $presenter)
    {
        $this->beConstructedWith($presenter);
    }

    function it_is_a_presenter()
    {
        $this->shouldImplement('PhpSpec\Formatter\Presenter\Presenter');
    }

    function it_should_tag_strings()
    {
        $this->presentString('foo')->shouldReturn('<value>foo</value>');
    }

    function it_should_tag_values_from_the_decorated_presenter(Presenter $presenter)
    {
        $presenter->presentValue('foo')->willReturn('zfooz');
        $this->presentValue('foo')->shouldReturn('<value>zfooz</value>');
    }

    function it_should_return_presented_exceptions_from_the_decorated_presenter_unchanged(
        Presenter $presenter, \Exception $exception
    ) {
        $presenter->presentException($exception, true)->willReturn('exc');
        $this->presentException($exception, true)->shouldReturn('exc');
    }
}
