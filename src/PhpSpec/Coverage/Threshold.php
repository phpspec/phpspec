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
 * A --coverage-min percentage as it was written: to so many places, and as a
 * whole number of its last place, so it is compared without a float.
 */
final readonly class Threshold
{
    public function __construct(public float $percent) {}

    /**
     * The places it is written to: 90.18 has two, 100 none.
     */
    public function places(): int
    {
        $written = rtrim(rtrim(sprintf('%.10F', $this->percent), '0'), '.');
        $point = strpos($written, '.');

        return $point === false ? 0 : strlen($written) - $point - 1;
    }

    /**
     * The threshold in units of its last place: 90.18 is 9018 hundredths.
     */
    public function units(): int
    {
        return (int) round($this->percent * 10 ** $this->places());
    }
}
