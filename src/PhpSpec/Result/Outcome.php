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

/**
 * How one example or one scenario ended, said once for every report: its
 * name, its state, what went wrong or why it was left out, where it is written
 * and where it went wrong, both named from the project root, and how long it
 * took.
 */
final readonly class Outcome
{
    /**
     * @param list<string> $titles the titles that lead to it, its own last: describe and contexts, or feature and scenario
     * @param string|null $message what went wrong, for one that failed, errored or lacks a step definition
     * @param string|null $reason why it was left pending or skipped, when it said, or that it checked nothing
     * @param string|null $step the step that decided a scenario, when one did
     */
    public function __construct(
        public Kind $kind,
        public array $titles,
        public State $state,
        public ?string $message,
        public ?string $reason,
        public ?Location $failedAt,
        public ?Location $writtenAt,
        public float $duration,
        public ?string $step = null,
    ) {}

    /**
     * Every title that leads to it, joined, as --filter matches it.
     */
    public function name(): string
    {
        return implode(' > ', $this->titles);
    }

    public function title(): string
    {
        return $this->titles === [] ? '' : $this->titles[array_key_last($this->titles)];
    }
}
