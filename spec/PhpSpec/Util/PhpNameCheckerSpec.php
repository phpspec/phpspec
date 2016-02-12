<?php

namespace spec\PhpSpec\Util;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class PhpNameCheckerSpec extends ObjectBehavior
{
    function it_is_restriction_provider()
    {
        $this->shouldHaveType('PhpSpec\Util\VoterInterface');
    }

    function it_returns_true_for_not_php_restricted_name()
    {
        $this->supports('foo')->shouldReturn(true);
    }

    function it_returns_false_for_php_restricted_name()
    {
        $this->supports('function')->shouldReturn(false);
    }

    function it_returns_false_for_php_predefined_constant()
    {
        $this->supports('__CLASS__')->shouldReturn(false);
    }
}
