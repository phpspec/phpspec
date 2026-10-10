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
 * Tracks the step's state (passed, failure, error, pending, undefined, skipped)
 * and an optional error. A failure is an expectation that did not hold; an
 * error is a step whose code threw.
 */
final class StepResult implements Results
{
    use RaisedNotesTrait;

    /** @var StepError|null Error that occurred during step execution */
    private ?StepError $error = null;

    /** @var MatchResult|null The expectation that did not hold, when one failed */
    private ?MatchResult $match = null;

    /** @var string What the step printed while it ran */
    private string $output = '';

    /** @var float How long the step ran, in seconds */
    private float $duration = 0.0;

    /**
     * @param string $title the step description
     * @param string $state the outcome state: passed, failure, error, pending, undefined, or skipped
     * @param string|null $reason why the step was left pending or skipped, when it said
     */
    public function __construct(
        private readonly string $title,
        private readonly string $state, // passed, failure, error, pending, undefined, skipped
        private readonly ?string $reason = null,
    ) {}

    /**
     * Why the step was left pending or skipped, when it said; a step skipped
     * behind a failure said nothing.
     */
    public function getReason(): ?string
    {
        return $this->reason;
    }

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
     * Whether the step skipped itself, with skip() or by a hook's, rather than
     * being skipped behind a step that failed or was pending before it.
     */
    public function isSkippedForAReason(): bool
    {
        return $this->isSkipped() && $this->reason !== null;
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

    /**
     * Keeps the expectation that did not hold, so what was wanted and what was
     * had stay values a reader can compare, instead of a sentence to parse back.
     *
     * @param MatchResult $match the failing expectation
     */
    public function setMatch(MatchResult $match): void
    {
        $this->match = $match;
    }

    /**
     * Returns the expectation that did not hold, or null when the step did not
     * fail on one (it threw, or it passed).
     */
    public function getMatch(): ?MatchResult
    {
        return $this->match;
    }

    /**
     * Checks whether this step's code threw, as opposed to an expectation
     * failing.
     */
    public function isError(): bool
    {
        return $this->state === 'error';
    }

    /**
     * Stores what the step printed while it ran: when a step drives a process
     * of its own, what that process said is the whole of the diagnosis.
     *
     * @param string $output everything the step printed
     */
    public function setOutput(string $output): void
    {
        $this->output = $output;
    }

    /**
     * Returns what the step printed while it ran.
     */
    public function getOutput(): string
    {
        return $this->output;
    }

    /**
     * This step errored by something that ran after it, keeping what it
     * printed, how long it took and the notes it raised.
     */
    public function erroredBy(StepError $error): self
    {
        $errored = new self($this->title, 'error');
        $errored->error = $error;
        $errored->output = $this->output;
        $errored->duration = $this->duration;
        $errored->warnings = $this->warnings;
        $errored->deprecations = $this->deprecations;
        $errored->notices = $this->notices;

        return $errored;
    }

    /**
     * @param float $duration elapsed time in seconds
     */
    public function setDuration(float $duration): void
    {
        $this->duration = $duration;
    }

    /**
     * Returns how long the step ran, in seconds: zero for a step that never ran.
     */
    public function getDuration(): float
    {
        return $this->duration;
    }
}
