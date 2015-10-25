<?php

namespace spec\PhpSpec\Formatter\Presenter\Exception;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class HtmlPhpSpecExceptionPresenterSpec extends ObjectBehavior
{
    function it_is_a_phpspec_exception_presenter()
    {
        $this->shouldImplement('PhpSpec\Formatter\Presenter\Exception\PhpSpecExceptionPresenter');
    }
}
