<?php

/*
 * This file is part of PhpSpec, A php toolset to drive emergent
 * design by specification.
 *
 * (c) Marcello Duarte <marcello.duarte@gmail.com>
 * (c) Konstantin Kudryashov <ever.zet@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace spec\PhpSpec\Formatter\Presenter;

use PhpSpec\Formatter\Presenter\Exception\ExceptionPresenter;
use PhpSpec\Formatter\Presenter\Value\ValuePresenter;
use PhpSpec\ObjectBehavior;

class SimplePresenterSpec extends ObjectBehavior
{
    function let(ValuePresenter $valuePresenter, ExceptionPresenter $exceptionPresenter)
    {
        $this->beConstructedWith($valuePresenter, $exceptionPresenter);
    }

    function it_is_a_presenter()
    {
        $this->shouldImplement('PhpSpec\Formatter\Presenter\Presenter');
    }

    function it_returns_a_string_unchanged()
    {
        $message = 'this is a string';
        $this->presentString($message)->shouldReturn($message);
    }

    function it_should_be_a_proxy_for_an_exception_presenter(
        ExceptionPresenter $exceptionPresenter, \Exception $exception
    ) {
        $result = 'this is the result';
        $exceptionPresenter->presentException($exception, true)->willReturn($result);
        $this->presentException($exception, true)->shouldReturn($result);
    }

    function it_should_be_a_proxy_for_a_value_presenter(
        ValuePresenter $valuePresenter
    ) {
        $valuePresenter->presentValue('foo')->willReturn('zfooz');
        $this->presentValue('foo')->shouldReturn('zfooz');
    }
}
