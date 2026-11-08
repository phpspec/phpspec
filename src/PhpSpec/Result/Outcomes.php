<?php

/*
 * This file is part of PhpSpec, A php toolset to drive emergent
 * design by specification.
 *
 * (c) Marcello Duarte <marcello.duarte@gmail.com>
 * (c) Konstantin Kudryashov <ever.zet@gmail.com>
 * (c) Ciaran McNulty <ciaran@ciaranmcnulty.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpSpec\Result;

use PhpSpec\ProjectRoot;
use PhpSpec\Results;

/**
 * @internal
 * Every example and scenario in a result tree, each with its outcome. Only
 * the path leading to one is worked out here, as the tree is walked; how it
 * ended, the result says itself. A spec file adds nothing to the path, as
 * --filter and example ids leave it out; a context, a feature add their title.
 */
final readonly class Outcomes
{
    public function __construct(private ProjectRoot $root) {}

    /**
     * @param callable(Outcome, ExampleResult|ScenarioResult): void $visit told of each, with the result it came from
     */
    public function walk(Results $results, callable $visit): void
    {
        $this->visit($results, [], '', null, $visit);
    }

    /**
     * @param list<string> $titles
     * @param callable(Outcome, ExampleResult|ScenarioResult): void $visit
     */
    private function visit(Results $node, array $titles, string $featurePath, ?ContextResult $context, callable $visit): void
    {
        if ($node instanceof ExampleResult) {
            $visit($node->outcome($context?->carriesItsError($node) ? $titles : [...$titles, $node->getTitle()], $this->root), $node);

            return;
        }

        if ($node instanceof ScenarioResult) {
            $visit($node->outcome([...$titles, $node->getTitle()], $featurePath, $this->root), $node);

            return;
        }

        if ($node instanceof FeatureResult) {
            [$titles, $featurePath] = [[...$titles, $node->getTitle()], $node->getPath()];
        } elseif ($node instanceof ContextResult) {
            $titles = [...$titles, $node->getTitle()];
        }

        foreach ($node->getResults() as $child) {
            $this->visit($child, $titles, $featurePath, $node instanceof ContextResult ? $node : null, $visit);
        }
    }
}
