<?php

namespace spec\PhpSpec\Util;

use PhpSpec\Exception\Example\SkippingException;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

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

    function it_returns_false_for_php_restricted_name()
    {
        $this->skipIfPhp7();
        $this->isNameValid('function')->shouldReturn(false);
    }

    function it_returns_false_for_php_predefined_constant()
    {
        $this->skipIfPhp7();
        $this->isNameValid('__CLASS__')->shouldReturn(false);
    }

    function it_returns_false_for_php_restricted_name_case_insensitive()
    {
        $this->skipIfPhp7();
        $this->isNameValid('instanceof')->shouldReturn(false);
        $this->isNameValid('instanceOf')->shouldReturn(false);
    }

    function it_returns_false_for___halt_compiler_function()
    {
        $this->isNameValid('__halt_compiler')->shouldReturn(false);
    }

    private function skipIfPhp7()
    {
        if (\PHP_VERSION_ID >= 70000) {
            throw new SkippingException('Reserved keywords list in PHP 7 does not include most of PHP 5.6 keywords');
        }
    }

}
