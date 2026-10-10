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

namespace PhpSpec\Console\Command\Refactor;

/**
 * @internal
 * A refactoring the model means to make: the technique, why, in its own
 * words, and the baby steps it takes, in order.
 */
final readonly class RefactorPlan
{
    /**
     * @param list<string> $steps
     */
    public function __construct(
        public string $technique,
        public string $rationale,
        public array $steps,
    ) {}
}
