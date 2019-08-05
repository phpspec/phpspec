<?php

namespace spec\PhpSpec\Runner\Maintainer;

use PhpSpec\Formatter\Presenter\Presenter;
use PhpSpec\Loader\Node\ExampleNode;
use PhpSpec\Matcher\Matcher;
use PhpSpec\ObjectBehavior;
use PhpSpec\Runner\CollaboratorManager;
use PhpSpec\Runner\MatcherManager;
use PhpSpec\Specification;

class MatchersMaintainerSpec extends ObjectBehavior
{
    function it_should_add_default_matchers_to_the_matcher_manager(
        Presenter $presenter, ExampleNode $example, Specification $context,
        MatcherManager $matchers, CollaboratorManager $collaborators, Matcher $matcher)
    {
        $this->beConstructedWith($presenter, array($matcher));
        $this->prepare($example, $context, $matchers, $collaborators);

        $matchers->replace(array($matcher))->shouldHaveBeenCalled();
    }
}
