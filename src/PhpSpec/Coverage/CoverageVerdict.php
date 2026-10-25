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
     * @param Percentage $percentage executable source lines covered
     * @param float|null $required the --coverage-min threshold, or null when none was asked for
     * @param list<string> $unrecorded the source files with no coverage recorded during an example or scenario, which the percentage cannot speak for
     */
    public function __construct(
        public Percentage $percentage,
        public ?float $required = null,
        public array $unrecorded = [],
    ) {}

    /**
     * Whether the run met what was asked of it. A run with no threshold has
     * nothing to fall short of; one missing the coverage of a source file
     * cannot meet one.
     */
    public function met(): bool
    {
        return $this->required === null || ($this->unrecorded === [] && $this->percentage->reaches($this->required));
    }

    /**
     * The percentage cut to the places the threshold is written to, one at
     * least, so it reads below the threshold exactly when it is.
     */
    public function shown(): string
    {
        return $this->percentage->cut($this->places());
    }

    /**
     * The threshold to the same places as the percentage beside it.
     */
    public function shownRequired(): ?string
    {
        return $this->required === null ? null : number_format($this->required, $this->places(), '.', '');
    }

    /**
     * The exit code this verdict demands, or null when it demands none.
     */
    public function exitCode(): ?int
    {
        return $this->met() ? null : 1;
    }

    private function places(): int
    {
        $written = $this->required === null ? '' : (string) $this->required;
        $point = strpos($written, '.');

        return max(1, $point === false ? 0 : strlen($written) - $point - 1);
    }
}
