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

namespace PhpSpec\StoryBDD;

/**
 * @internal
 * Data class representing a parsed Gherkin Scenario with its title, ordered steps, and tags.
 */
readonly class ScenarioNode
{
    /**
     * Creates a ScenarioNode with its title, steps, and optional tags.
     *
     * @param string $title the scenario title from the "Scenario:" line
     * @param StepNode[] $steps ordered list of StepNode instances
     * @param string[] $tags tags applied to the scenario
     * @param int $line line number of the "Scenario:"/"Scenario Outline:" keyword in the feature file (0 = unknown)
     * @param int|null $exampleLine line number of the examples table row for outline-expanded scenarios, or null
     */
    public function __construct(
        public string $title,
        /** @var StepNode[] */
        public array $steps,
        /** @var string[] */
        public array $tags = [],
        public int $line = 0,
        public ?int $exampleLine = null,
    ) {}
}
