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

use PhpSpec\Formatter\Presenter\Value\TypePresenter;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class SimplePresenterSpec extends ObjectBehavior
{
    function it_is_a_presenter()
    {
        $this->shouldImplement('PhpSpec\Formatter\Presenter\Presenter');
    }

    function it_returns_a_string_unchanged()
    {
        $message = 'this is a string';
        $this->presentString($message)->shouldReturn($message);
    }

    function it_should_accept_a_type_presenter(TypePresenter $typePresenter)
    {
        $this->addTypePresenter($typePresenter)->shouldReturn(null);
    }

    function it_should_call_supports_on_value_presenters_until_one_returns_true(
        TypePresenter $presenter1, TypePresenter $presenter2, TypePresenter $presenter3
    ) {
        $value = 'blah';

        $presenter1->getPriority()->willReturn(3);
        $presenter2->getPriority()->willReturn(2);
        $presenter3->getPriority()->willReturn(1);

        $this->addTypePresenter($presenter1);
        $this->addTypePresenter($presenter2);
        $this->addTypePresenter($presenter3);

        $presenter1->supports($value)->willReturn(false)->shouldBeCalled();
        $presenter2->supports($value)->willReturn(true)->shouldBeCalled();
        $presenter2->present(Argument::any())->shouldBeCalled();
        $presenter3->supports($value)->shouldNotBeCalled();

        $this->presentValue($value);
    }

    function it_should_order_presenters_by_their_priority_in_descending_order(
        TypePresenter $presenter1, TypePresenter $presenter2
    ) {
        $value = 'foo';

        $presenter1->getPriority()->willReturn(10);
        $presenter2->getPriority()->willReturn(20);

        $this->addTypePresenter($presenter1);
        $this->addTypePresenter($presenter2);

        $presenter1->supports($value)->shouldBeCalled()->willReturn(true);
        $presenter2->supports($value)->shouldBeCalled();
        $presenter1->present(Argument::any())->shouldBeCalled();

        $this->presentValue($value);
    }

    function it_should_call_present_on_a_supporting_type_presenter(TypePresenter $typePresenter)
    {
        $value = 'blah';

        $typePresenter->supports($value)->willReturn(true);
        $typePresenter->present($value)->shouldBeCalled();

        $this->addTypePresenter($typePresenter);
        $this->presentValue($value);
    }

    function it_should_return_the_type_presenter_presented_value(TypePresenter $typePresenter)
    {
        $value = 'blah';
        $presented = $value.'presented';

        $typePresenter->supports($value)->willReturn(true);
        $typePresenter->present($value)->willReturn($presented);

        $this->addTypePresenter($typePresenter);
        $this->presentValue($value)->shouldReturn($presented);
    }

    function it_returns_a_default_when_no_type_presenters_support_the_value()
    {
        $this->presentValue('blah')->shouldReturn('[string:blah]');
    }
}
