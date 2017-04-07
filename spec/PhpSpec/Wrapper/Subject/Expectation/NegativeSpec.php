<?php

namespace spec\PhpSpec\Wrapper\Subject\Expectation;

use PhpSpec\ObjectBehavior;
use PhpSpec\Wrapper\Subject;
use Prophecy\Argument;

use PhpSpec\Matcher\Matcher;

class NegativeSpec extends ObjectBehavior
{
    function let(Matcher $matcher)
    {
        $this->beConstructedWith($matcher);
    }

    function it_calls_a_negative_match_on_matcher(Matcher $matcher)
    {
        $alias = 'somealias';
        $subject = 'subject';
        $arguments = array();

        $matcher->negativeMatch($alias, $subject, $arguments)->shouldBeCalled();
        $this->match($alias, $subject, $arguments);
    }
}
