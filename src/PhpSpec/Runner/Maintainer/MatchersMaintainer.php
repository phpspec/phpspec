<?php

namespace PhpSpec\Runner\Maintainer;

use PhpSpec\Loader\Node\ExampleNode;
use PhpSpec\SpecificationInterface;
use PhpSpec\Runner\MatcherManager;
use PhpSpec\Runner\CollaboratorManager;

use PhpSpec\Formatter\Presenter\PresenterInterface;
use PhpSpec\Wrapper\Unwrapper;
use PhpSpec\Matcher;

class MatchersMaintainer implements MaintainerInterface
{
    private $presenter;
    private $unwrapper;

    public function __construct(PresenterInterface $presenter, Unwrapper $unwrapper)
    {
        $this->presenter = $presenter;
        $this->unwrapper = $unwrapper;
    }

    public function supports(ExampleNode $example)
    {
        return true;
    }

    public function prepare(ExampleNode $example, SpecificationInterface $context,
                            MatcherManager $matchers, CollaboratorManager $collaborators)
    {
        $matchers->add(new Matcher\IdentityMatcher($this->presenter));
        $matchers->add(new Matcher\ComparisonMatcher($this->presenter));
        $matchers->add(new Matcher\ThrowMatcher($this->unwrapper, $this->presenter));
        $matchers->add(new Matcher\TypeMatcher($this->presenter));
        $matchers->add(new Matcher\ObjectStateMatcher($this->presenter));
        $matchers->add(new Matcher\ScalarMatcher($this->presenter));
        $matchers->add(new Matcher\ArrayCountMatcher($this->presenter));
        $matchers->add(new Matcher\ArrayKeyMatcher($this->presenter));
        $matchers->add(new Matcher\ArrayContainMatcher($this->presenter));
        $matchers->add(new Matcher\StringStartMatcher($this->presenter));
        $matchers->add(new Matcher\StringEndMatcher($this->presenter));
        $matchers->add(new Matcher\StringRegexMatcher($this->presenter));

        if (!$context instanceof Matcher\MatchersProviderInterface) {
            return;
        }

        foreach ($context->getMatchers() as $name => $matcher) {
            if ($matcher instanceof Matcher\MatcherInterface) {
                $matchers->add($matcher);
            } else {
                $matchers->add(new Matcher\CallbackMatcher(
                    $name, $matcher, $this->presenter
                ));
            }
        }
    }

    public function teardown(ExampleNode $example, SpecificationInterface $context,
                             MatcherManager $matchers, CollaboratorManager $collaborators)
    {
    }

    public function getPriority()
    {
        return 50;
    }
}
