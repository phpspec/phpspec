<?php

declare(strict_types=1);

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

use Closure;

/**
 * @internal
 * Hands a value to a closure from a file that declares strict types, so the
 * closure's parameter type holds the value as PHP holds the arguments a
 * strictly typed file passes.
 */
final class StrictCall
{
    public function __invoke(Closure $taking, mixed $value): mixed
    {
        return $taking($value);
    }
}
