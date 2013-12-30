<?php

/*
 * This file is part of PhpSpec, A php toolset to drive emergent
 * design by specification.
 *
 * (c) Marcello Duarte <marcello.duarte@gmail.com>
 * (c) Konstantin Kudryashov <ever.zet@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpSpec\Runner\Maintainer;

use PhpSpec\Loader\Node\ExampleNode;
use PhpSpec\SpecificationInterface;
use PhpSpec\Runner\MatcherManager;
use PhpSpec\Runner\CollaboratorManager;

use PhpSpec\Formatter\Presenter\PresenterInterface;
use PhpSpec\Wrapper\Unwrapper;
use PhpSpec\Matcher;

/**
 * Class MatchersMaintainer
 * @package PhpSpec\Runner\Maintainer
 */
class MatchersMaintainer implements MaintainerInterface
{
    /**
     * @var \PhpSpec\Formatter\Presenter\PresenterInterface
     */
    private $presenter;
    /**
     * @var \PhpSpec\Wrapper\Unwrapper
     */
    private $unwrapper;

    /**
     * @param PresenterInterface $presenter
     * @param Unwrapper          $unwrapper
     */
    public function __construct(PresenterInterface $presenter, Unwrapper $unwrapper)
    {
        $this->presenter = $presenter;
        $this->unwrapper = $unwrapper;
    }

    /**
     * @param ExampleNode $example
     *
     * @return bool
     */
    public function supports(ExampleNode $example)
    {
        return true;
    }

    /**
     * @param ExampleNode            $example
     * @param SpecificationInterface $context
     * @param MatcherManager         $matchers
     * @param CollaboratorManager    $collaborators
     */
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

    /**
     * @param ExampleNode            $example
     * @param SpecificationInterface $context
     * @param MatcherManager         $matchers
     * @param CollaboratorManager    $collaborators
     */
    public function teardown(ExampleNode $example, SpecificationInterface $context,
                             MatcherManager $matchers, CollaboratorManager $collaborators)
    {
    }

    /**
     * @return int
     */
    public function getPriority()
    {
        return 50;
    }
}
