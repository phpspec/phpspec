<?php

namespace spec\PhpSpec\Formatter\Presenter\Exception;

use PhpSpec\Formatter\Presenter\Differ\Differ;
use PhpSpec\Formatter\Presenter\Exception\CallArgumentsPresenter;
use PhpSpec\Formatter\Presenter\Exception\ExceptionElementPresenter;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class SimpleExceptionPresenterSpec extends ObjectBehavior
{
    function let(
        Differ $differ, ExceptionElementPresenter $exceptionElementPresenter,
        CallArgumentsPresenter $callArgumentsPresenter
    ) {
        $this->beConstructedWith($differ, $exceptionElementPresenter, $callArgumentsPresenter);
    }

    function it_is_an_exception_presenter()
    {
        $this->shouldImplement('PhpSpec\Formatter\Presenter\Exception\ExceptionPresenter');
    }
}
