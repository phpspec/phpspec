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

namespace PhpSpec;

final readonly class InProcessResult
{
    /**
     * @param int $exitCode the process exit code (0 = success)
     * @param string $output the captured console output
     */
    public function __construct(
        public int $exitCode,
        public string $output,
    ) {}
}
