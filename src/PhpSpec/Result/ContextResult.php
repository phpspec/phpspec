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
 * Aggregates example results for a single describe/context block.
 *
 * Tracks a title, child example results, and an optional error state for the context itself.
 */
class ContextResult implements Results
{
    /** @var mixed Error that occurred at the context level, if any */
    private mixed $error;

    /**
     * @param string $title the context description
     * @param array<Results> $exampleResults child ExampleResult, ContextResult, or other Results instances
     */
    public function __construct(private string $title, private array $exampleResults) {}

    /**
     * Returns the context description.
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Returns the child example and nested context results.
     */
    public function getResults(): array
    {
        return $this->exampleResults;
    }

    /**
     * Indicates this result represents a context (not an example).
     */
    public function isContext(): bool
    {
        return true;
    }

    /**
     * Records a context-level error.
     *
     * @param mixed $error the error to store
     */
    public function setError($error): void
    {
        $this->error = $error;
    }

    /**
     * Checks whether this context has an error.
     */
    public function isError(): bool
    {
        return isset($this->error);
    }

    /**
     * Returns the context-level error, or null if none.
     */
    public function getError(): mixed
    {
        return $this->error;
    }
}
