<?php

namespace spec\PhpSpec\Runner;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

use PhpSpec\Formatter\Presenter\Presenter;
use PhpSpec\Matcher\Matcher;

class MatcherManagerSpec extends ObjectBehavior
{
    function let(Presenter $presenter)
    {
        $this->beConstructedWith($presenter);
    }

    function it_searches_in_registered_matchers(Matcher $matcher)
    {
        $matcher->getPriority()->willReturn(0);
        $matcher->supports('startWith', 'hello, world', array('hello'))->willReturn(true);

        $this->add($matcher);
        $this->find('startWith', 'hello, world', array('hello'))->shouldReturn($matcher);
    }

    function it_searches_matchers_by_their_priority(
        Matcher $matcher1, Matcher $matcher2
    ) {
        $matcher1->getPriority()->willReturn(2);
        $matcher1->supports('startWith', 'hello, world', array('hello'))->willReturn(true);
        $matcher2->getPriority()->willReturn(5);
        $matcher2->supports('startWith', 'hello, world', array('hello'))->willReturn(true);

        $this->add($matcher1);
        $this->add($matcher2);

        $this->find('startWith', 'hello, world', array('hello'))->shouldReturn($matcher2);
    }

    function it_throws_MatcherNotFoundException_if_matcher_not_found()
    {
        $this->shouldThrow('PhpSpec\Exception\Wrapper\MatcherNotFoundException')
            ->duringFind('startWith', 'hello, world', array('hello'));
    }
}
