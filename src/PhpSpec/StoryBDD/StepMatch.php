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

namespace PhpSpec\StoryBDD;

/**
 * Value object returned when step text matches a registered pattern.
 * Contains the step closure, captured arguments, and the original pattern.
 */
readonly class StepMatch
{
    /**
     * Creates a StepMatch with the matched closure, captured arguments, and source pattern.
     *
     * @param \Closure $callback the step closure to execute
     * @param array $args captured values from placeholder groups in the pattern
     * @param string $pattern the original registered step pattern
     */
    public function __construct(
        public \Closure $callback,
        public array $args,
        public string $pattern,
    ) {}
}
