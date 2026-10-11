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

use RuntimeException;

/**
 * @internal
 * Ctrl-C while a plan is carried out: the step under way is undone and the
 * run stops green.
 */
final class InterruptedException extends RuntimeException
{
    /**
     * @param bool $stepUndone whether the step under way had been written, and so was undone
     */
    public function __construct(public readonly bool $stepUndone = false)
    {
        parent::__construct('Interrupted.');
    }
}
