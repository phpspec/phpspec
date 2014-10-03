<?php

namespace spec\PhpSpec\Runner\Maintainer;

use PhpSpec\Formatter\Presenter\PresenterInterface;
use PhpSpec\Loader\Node\ExampleNode;
use PhpSpec\Matcher\MatcherInterface;
use PhpSpec\ObjectBehavior;
use PhpSpec\Runner\CollaboratorManager;
use PhpSpec\Runner\MatcherManager;
use PhpSpec\SpecificationInterface;
use PhpSpec\Wrapper\Unwrapper;
use Prophecy\Argument;

class MatchersMaintainerSpec extends ObjectBehavior
{
    function let(PresenterInterface $presenter, Unwrapper $unwrapper)
    {
        $this->beConstructedWith($presenter, $unwrapper);
    }

    function it_should_accept_a_matcher(MatcherInterface $matcher)
    {
        $this->addMatcher($matcher);
    }

    function it_should_pass_the_matcher_to_the_matcher_manager(ExampleNode $example,
        SpecificationInterface $context, MatcherManager $matchers, CollaboratorManager $collaborators,
        MatcherInterface $matcher)
    {
        $this->addMatcher($matcher);
        $this->prepare($example, $context, $matchers, $collaborators);

        $matchers->add($matcher)->shouldHaveBeenCalled();
    }
}
