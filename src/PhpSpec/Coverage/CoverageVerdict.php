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
 * What a coverage run concluded: how much of the source the specs executed, and
 * the threshold it was asked to meet. A plain value, so the one place that
 * measured it says it once and everything downstream (the console line, the exit
 * code, the agent document) derives from the same statement.
 */
final readonly class CoverageVerdict
{
    /**
     * @param float $percent executable source lines covered, as a percentage
     * @param float|null $required the --coverage-min threshold, or null when none was asked for
     */
    public function __construct(
        public float $percent,
        public ?float $required = null,
    ) {}

    /**
     * Whether the run met what was asked of it. A run with no threshold has
     * nothing to fall short of.
     */
    public function met(): bool
    {
        return $this->required === null || $this->percent >= $this->required;
    }

    /**
     * The exit code this verdict demands, or null when it demands none.
     */
    public function exitCode(): ?int
    {
        return $this->met() ? null : 1;
    }
}
