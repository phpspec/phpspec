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
use PhpSpec\Matcher\CallbackMatcher;
use PhpSpec\Matcher\Matcher;
use PhpSpec\Matcher\MatchersProvider;
use PhpSpec\Specification;
use PhpSpec\Runner\MatcherManager;
use PhpSpec\Runner\CollaboratorManager;
use PhpSpec\Formatter\Presenter\Presenter;

final class MatchersMaintainer implements Maintainer
{
    /**
     * @var Presenter
     */
    private $presenter;

    /**
     * @var Matcher[]
     */
    private $defaultMatchers = array();

    /**
     * @param Presenter $presenter
     * @param Matcher[] $matchers
     */
    public function __construct(Presenter $presenter, array $matchers)
    {
        $this->presenter = $presenter;
        $this->defaultMatchers = $matchers;
        @usort($this->defaultMatchers, function (Matcher $matcher1, Matcher $matcher2) {
            return $matcher2->getPriority() - $matcher1->getPriority();
        });
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
     * @param Specification $context
     * @param MatcherManager         $matchers
     * @param CollaboratorManager    $collaborators
     */
    public function prepare(
        ExampleNode $example,
        Specification $context,
        MatcherManager $matchers,
        CollaboratorManager $collaborators
    ) {

        $matchers->replace($this->defaultMatchers);

        if (!$context instanceof MatchersProvider) {
            return;
        }

        foreach ($context->getMatchers() as $name => $matcher) {
            if ($matcher instanceof Matcher) {
                $matchers->add($matcher);
            } else {
                $matchers->add(new CallbackMatcher(
                    $name,
                    $matcher,
                    $this->presenter
                ));
            }
        }
    }

    /**
     * @param ExampleNode            $example
     * @param Specification $context
     * @param MatcherManager         $matchers
     * @param CollaboratorManager    $collaborators
     */
    public function teardown(
        ExampleNode $example,
        Specification $context,
        MatcherManager $matchers,
        CollaboratorManager $collaborators
    ) {
    }

    /**
     * @return int
     */
    public function getPriority()
    {
        return 50;
    }
}
