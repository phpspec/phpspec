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

namespace PhpSpec\Mock;

/**
 * @internal
 * What a double's parameter holds when the call left it out and its default
 * is one the double's signature cannot hold (an object, a closure): the double
 * builds that default in its place. A parameter left out before a named
 * argument holds it too, which a count of the arguments cannot tell.
 */
enum LeftOut
{
    case Argument;
}
