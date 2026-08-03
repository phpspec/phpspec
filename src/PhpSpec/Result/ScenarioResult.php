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
 * @internal
 * Aggregates step results for a single scenario in Story BDD mode.
 */
final readonly class ScenarioResult implements Results
{
    /**
     * @param string $title the scenario description
     * @param array<StepResult> $stepResults child StepResult instances
     * @param int $line the line its "Scenario:" keyword sits on, or the examples
     *                  table row for an outline expansion; 0 when unknown
     */
    public function __construct(
        private string $title,
        private array $stepResults,
        private int $line = 0,
    ) {}

    /**
     * The line that addresses this scenario in a "file.feature:LINE" run, or 0
     * when the result was rebuilt without one.
     */
    public function getLine(): int
    {
        return $this->line;
    }

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
