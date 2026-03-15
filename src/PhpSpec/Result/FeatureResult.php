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
 * Aggregates scenario results for a single feature file in Story BDD mode.
 */
final readonly class FeatureResult implements Results
{
    /**
     * @param string $title the feature description
     * @param array $scenarioResults child ScenarioResult instances
     * @param string $path the feature file path
     */
    public function __construct(
        private string $title,
        private array $scenarioResults,
        private string $path = '',
    ) {}

    /**
     * Returns the feature description.
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Returns the feature file path.
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Returns the child scenario results.
     */
    public function getResults(): array
    {
        return $this->scenarioResults;
    }
}
