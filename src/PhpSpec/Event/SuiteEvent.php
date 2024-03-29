<?php

/*
 * This file is part of PhpSpec, A php toolset to drive emergent
 * design by specification.
 *
 * (c) Marcello Duarte <marcello.duarte@gmail.com>
 * (c) Konstantin Kudryashov <ever.zet@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpSpec\Event;

use PhpSpec\Loader\Suite;

/**
 * Class SuiteEvent holds information about the suite event
 */
class SuiteEvent extends BaseEvent implements PhpSpecEvent
{
    private bool $worthRerunning = false;

    public function __construct(
        private Suite $suite,
        private float $time = 0.0,
        private int $result = 0
    )
    {
    }

    public function getSuite(): Suite
    {
        return $this->suite;
    }

    public function getTime(): float
    {
        return $this->time;
    }

    public function getResult(): int
    {
        return $this->result;
    }

    public function isWorthRerunning(): bool
    {
        return $this->worthRerunning;
    }

    public function markAsWorthRerunning(): void
    {
        $this->worthRerunning = true;
    }

    public function markAsNotWorthRerunning(): void
    {
        $this->worthRerunning = false;
    }
}
