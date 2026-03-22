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

namespace PhpSpec\Logging;

/**
 * @internal
 * Defines the contract for objects that can produce a log representation of themselves.
 */
interface Loggable
{
    /**
     * Returns a string representation suitable for logging.
     *
     * @return string the log output
     */
    public function getLog(): string;
}
