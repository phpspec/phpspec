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
     * @param array<string, string|array{error: string}> $attachments context the
     *        scenario handed over about itself, read before its teardown ran
     */
    public function __construct(
        private string $title,
        private array $stepResults,
        private int $line = 0,
        private array $attachments = [],
    ) {}

    /**
     * Context the scenario handed over about itself: the log it watched, the
     * output of a process it drove. Empty unless something was attached, and
     * only ever read for a scenario that needed attention.
     *
     * @return array<string, string|array{error: string}>
     */
    public function getAttachments(): array
    {
        return $this->attachments;
    }

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

    /**
     * The step that decides how the scenario ended: its first step that fails
     * the run, whatever came before it, so the verdict agrees with the exit
     * code; else its first step left pending or skipped on purpose; none for a
     * scenario that passed.
     */
    public function decidingStep(): ?StepResult
    {
        $leftOut = null;

        foreach ($this->stepResults as $step) {
            if ($step->state()->failsTheRun()) {
                return $step;
            }

            if ($leftOut === null && ($step->isPending() || $step->isSkippedForAReason())) {
                $leftOut = $step;
            }
        }

        return $leftOut;
    }

    public function state(): State
    {
        return $this->decidingStep()?->state() ?? State::Passed;
    }

    public function message(): ?string
    {
        return $this->decidingStep()?->message();
    }

    public function reason(): ?string
    {
        return $this->decidingStep()?->getReason();
    }

    public function failedAt(): ?Location
    {
        return $this->decidingStep()?->failedAt();
    }

    /**
     * How long its steps ran, in seconds.
     */
    public function getDuration(): float
    {
        return array_sum(array_map(static fn(StepResult $step): float => $step->getDuration(), $this->stepResults));
    }
}
