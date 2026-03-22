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

use PhpSpec\Results;

/**
 * Aggregates step results for a single scenario in Story BDD mode.
 */
final readonly class ScenarioResult implements Results
{
    /**
     * @param string $title the scenario description
     * @param array<StepResult> $stepResults child StepResult instances
     */
    public function __construct(
        private string $title,
        private array $stepResults,
    ) {}

    /**
     * Returns the scenario description.
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Returns the child step results.
     */
    public function getResults(): array
    {
        return $this->stepResults;
    }
}
