<?php

namespace spec\PhpSpec\Util;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class ReservedWordsMethodNameCheckerSpec extends ObjectBehavior
{
    function it_is_restriction_provider()
    {
        $this->shouldHaveType('PhpSpec\Util\NameCheckerInterface');
    }

    function it_returns_true_for_not_php_restricted_name()
    {
        $this->isNameValid('foo')->shouldReturn(true);
    }

    function it_returns_false_for_php_restricted_name()
    {
        $this->isNameValid('function')->shouldReturn(false);
    }

    function it_returns_false_for_php_predefined_constant()
    {
        $this->isNameValid('__CLASS__')->shouldReturn(false);
    }

    function it_returns_false_for_php_restricted_name_case_insensitive()
    {
        $this->isNameValid('instanceof')->shouldReturn(false);
        $this->isNameValid('instanceOf')->shouldReturn(false);
    }
}
