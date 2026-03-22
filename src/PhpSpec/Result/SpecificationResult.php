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
 * Aggregates context and example results for a single specification file.
 */
final readonly class SpecificationResult implements Results
{
    /**
     * @param string $title the specification file title
     * @param array<Results> $exampleResults child ContextResult, ExampleResult, and other Results instances
     */
    public function __construct(private string $title, private array $exampleResults) {}

    /**
     * Returns the specification file title.
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Returns the child context and example results.
     */
    public function getResults(): array
    {
        return $this->exampleResults;
    }
}
