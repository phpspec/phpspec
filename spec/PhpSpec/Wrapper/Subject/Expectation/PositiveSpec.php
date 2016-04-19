<?php

namespace spec\PhpSpec\Wrapper\Subject\Expectation;

use PhpSpec\Matcher\Matcher;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class PositiveSpec extends ObjectBehavior
{
    function let(Matcher $matcher)
    {
        $this->beConstructedWith($matcher);
    }

    function it_calls_a_positive_match_on_matcher(Matcher $matcher)
    {
        $alias = 'somealias';
        $subject = 'subject';
        $arguments = array();

        $matcher->positiveMatch($alias, $subject, $arguments)->shouldBeCalled();
        $this->match($alias, $subject, $arguments);
    }
}
