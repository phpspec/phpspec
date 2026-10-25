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

namespace PhpSpec\Coverage;

/**
 * @internal
 * The share of executable lines covered, worked out from the counts: shown
 * cut, never rounded up to more than was covered, and held to a threshold
 * with no float in between.
 */
final readonly class Percentage
{
    public function __construct(
        public int $covered,
        public int $executable,
    ) {}

    public function value(): float
    {
        return $this->executable > 0 ? $this->covered * 100 / $this->executable : 0.0;
    }

    /**
     * The percentage to so many places, the rest cut off: 2502 of 2503 lines
     * is 99.9, never 100.0.
     */
    public function cut(int $places = 1): string
    {
        $scale = 10 ** $places;
        $units = $this->executable > 0 ? intdiv($this->covered * 100 * $scale, $this->executable) : 0;

        return intdiv($units, $scale) . '.' . str_pad((string) ($units % $scale), $places, '0', STR_PAD_LEFT);
    }

    /**
     * Whether it is the threshold or more. No executable lines reach no
     * threshold above zero.
     */
    public function reaches(float $percent): bool
    {
        if ($this->executable === 0) {
            return $percent <= 0.0;
        }

        $threshold = new Threshold($percent);

        return $this->covered * 100 * 10 ** $threshold->places() >= $threshold->units() * $this->executable;
    }

    public function complete(): bool
    {
        return $this->covered === $this->executable;
    }
}
