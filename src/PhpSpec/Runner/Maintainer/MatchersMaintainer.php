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
     * @param Matcher[] $matchers
     */
    public function __construct(
        private Presenter $presenter,
        private array $matchers
    )
    {
        @usort($this->matchers, function (Matcher $matcher1, Matcher $matcher2) {
            return $matcher2->getPriority() - $matcher1->getPriority();
        });
    }

    public function supports(ExampleNode $example): bool
    {
        return true;
    }

    public function prepare(
        ExampleNode $example,
        Specification $context,
        MatcherManager $matchers,
        CollaboratorManager $collaborators
    ): void {

        $matchers->replace($this->matchers);

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

    public function teardown(
        ExampleNode $example,
        Specification $context,
        MatcherManager $matchers,
        CollaboratorManager $collaborators
    ): void {
    }

    public function getPriority(): int
    {
        return 50;
    }
}
