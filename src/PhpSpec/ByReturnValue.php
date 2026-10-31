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

/**
 * @internal
 * A call to a PHP function that says how it went twice, by what it returns
 * and by a warning, made for what it returns alone: the warning reaches
 * neither the terminal nor the notes of the example it ran in.
 */
final class ByReturnValue
{
    /**
     * @template T
     * @param callable(): T $call
     * @return T
     */
    public function call(callable $call): mixed
    {
        set_error_handler(static fn(): bool => true, E_WARNING | E_NOTICE | E_USER_WARNING | E_USER_NOTICE);

        try {
            return $call();
        } finally {
            restore_error_handler();
        }
    }
}
