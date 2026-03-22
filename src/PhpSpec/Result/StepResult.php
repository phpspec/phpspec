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
use PhpSpec\StoryBDD\StepError;

/**
 * @internal
 * Holds the result of a single step (Given/When/Then) in Story BDD mode.
 *
 * Tracks the step's state (passed, failed, pending, undefined, skipped) and an optional error.
 */
final class StepResult implements Results
{
    /** @var StepError|null Error that occurred during step execution */
    private ?StepError $error = null;

    /**
     * @param string $title the step description
     * @param string $state the outcome state: passed, failure, pending, undefined, or skipped
     */
    public function __construct(
        private readonly string $title,
        private readonly string $state, // passed, failure, pending, undefined, skipped
    ) {}

    /**
     * Returns the step description.
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Returns an empty array since steps have no children.
     */
    public function getResults(): array
    {
        return [];
    }

    /**
     * Returns the outcome state string (passed, failed, pending, undefined, or skipped).
     */
    public function getState(): string
    {
        return $this->state;
    }

    /**
     * Checks whether this step passed.
     */
    public function isPassed(): bool
    {
        return $this->state === 'passed';
    }

    /**
     * Checks whether this step failed.
     */
    public function isFailure(): bool
    {
        return $this->state === 'failure';
    }

    /**
     * Checks whether this step is pending.
     */
    public function isPending(): bool
    {
        return $this->state === 'pending';
    }

    /**
     * Checks whether this step is undefined (no matching step definition).
     */
    public function isUndefined(): bool
    {
        return $this->state === 'undefined';
    }

    /**
     * Checks whether this step was skipped.
     */
    public function isSkipped(): bool
    {
        return $this->state === 'skipped';
    }

    /**
     * Stores an error that occurred during step execution.
     *
     * @param StepError $error the step error details
     */
    public function setError(StepError $error): void
    {
        $this->error = $error;
    }

    /**
     * Returns the step error, or null if the step did not error.
     */
    public function getError(): ?StepError
    {
        return $this->error;
    }
}
