<?php

namespace spec\PhpSpec\Util;

use PhpSpec\ObjectBehavior;

class ReservedWordsMethodNameCheckerSpec extends ObjectBehavior
{
    function it_is_restriction_provider()
    {
        $this->shouldHaveType('PhpSpec\Util\NameChecker');
    }

    function it_returns_true_for_not_php_restricted_name()
    {
        $this->isNameValid('foo')->shouldReturn(true);
    }

    function it_returns_false_for___halt_compiler_function()
    {
        $this->isNameValid('__halt_compiler')->shouldReturn(false);
    }
}
