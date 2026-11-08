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

namespace PhpSpec\Parallel;

/**
 * @internal
 * What a worker says on the wire besides a result: that it ran the file it
 * was handed and wants another, or that it reported everything it ran.
 */
enum WireMark
{
    case Ran;
    case Ended;
}
