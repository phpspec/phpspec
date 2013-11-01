<?php

namespace spec\PhpSpec\Wrapper\Subject\Expectation;

use PhpSpec\Matcher\MatcherInterface;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class NegativeExceptionSpec extends ObjectBehavior
{
    function let(MatcherInterface $matcher)
    {
        $this->beConstructedWith($matcher);
    }

    function it_calls_a_positive_match_on_matcher(MatcherInterface $matcher)
    {
        $alias = 'throw';
        $subject = 'subject';
        $arguments = array();

        $matcher->negativeMatch($alias, $subject, $arguments)->shouldBeCalled();
        $this->match($alias, $subject, $arguments);
    }
}
