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
 * The model's answer that nothing is worth refactoring, and why.
 */
final readonly class Declined
{
    /**
     * @param int $tokens how many tokens the model wrote for it
     */
    public function __construct(
        public string $reason,
        public int $tokens = 0,
    ) {}
}
