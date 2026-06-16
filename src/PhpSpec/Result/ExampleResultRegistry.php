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

use PhpSpec\Specification\ExampleError;

/**
 * @internal
 * Contract for registries that collect deferred matches and example results during spec execution.
 *
 * Implementations track match closures per example and aggregate ExampleResult instances.
 */
interface ExampleResultRegistry
{
    /**
     * Registers a deferred match closure to be evaluated after the example runs.
     *
     * @param \Closure $match the closure that produces a MatchResult
     */
    public function addMatch(\Closure $match): void;

    /**
     * Returns all registered deferred match closures for the current example.
     *
     * @return array<\Closure>
     */
    public function getMatches(): array;

    /**
     * Records a completed example result.
     *
     * @param ExampleResult $result the example result to store
     */
    public function addExampleResult(ExampleResult $result): void;

    /**
     * Clears all registered match closures, typically after an example completes.
     */
    public function resetMatches(): void;

    /**
     * Stores an error that occurred during example execution.
     *
     * @param ExampleError $error the error details
     */
    public function setError(ExampleError $error): void;

    /**
     * Checks whether an error has been recorded for the current example.
     */
    public function isError(): bool;

    /**
     * Registers a mock double created during example execution.
     *
     * @param mixed $double the mock double instance
     */
    public function addDouble(mixed $double): void;
}
