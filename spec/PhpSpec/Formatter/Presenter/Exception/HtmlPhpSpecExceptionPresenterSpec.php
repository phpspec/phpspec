<?php

namespace spec\PhpSpec\Formatter\Presenter\Exception;

use PhpSpec\ObjectBehavior;

class HtmlPhpSpecExceptionPresenterSpec extends ObjectBehavior
{
    function it_is_a_phpspec_exception_presenter()
    {
        $this->shouldImplement('PhpSpec\Formatter\Presenter\Exception\PhpSpecExceptionPresenter');
    }
}
