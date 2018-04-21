<?php

namespace spec\PhpSpec\Matcher;

use PhpSpec\ObjectBehavior;
use PhpSpec\Wrapper\Unwrapper;
use Prophecy\Argument;
use ArrayObject;

class TriggerMatcherSpec extends ObjectBehavior
{
    function let(Unwrapper $unwrapper)
    {
        $unwrapper->unwrapAll(Argument::any())->willReturnArgument();

        $this->beConstructedWith($unwrapper);
    }

    function it_supports_the_trigger_alias_for_object_and_exception_name()
    {
        $this->supports('trigger', '', array())->shouldReturn(true);
    }

    function it_accepts_a_method_during_which_an_error_should_be_triggered(ArrayObject $arr)
    {
        $arr->ksort()->will(function () { trigger_error('An error', E_USER_NOTICE); });

        $this->positiveMatch('trigger', $arr, array(E_USER_NOTICE, 'An error'))->during('ksort', array());
    }

    function it_accepts_a_method_during_which_any_error_should_be_triggered(ArrayObject $arr)
    {
        $arr->ksort()->will(function () { trigger_error('An error', E_USER_NOTICE); });

        $this->positiveMatch('trigger', $arr, array(null, null))->during('ksort', array());
    }

    function it_accepts_a_method_during_which_an_error_should_not_be_triggered(ArrayObject $arr)
    {
        $this->negativeMatch('trigger', $arr, array(E_USER_NOTICE, 'An error'))->during('ksort', array());
    }

    function it_accepts_a_method_during_which_any_error_should_not_be_triggered(ArrayObject $arr)
    {
        $this->negativeMatch('trigger', $arr, array(null, null))->during('ksort', array());
    }
}
