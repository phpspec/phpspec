<?php

/*
 * This file is part of PhpSpec, A php toolset to drive emergent
 * design by specification.
 *
 * (c) Marcello Duarte <marcello.duarte@gmail.com>
 * (c) Konstantin Kudryashov <ever.zet@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpSpec\Formatter\Presenter\Value;


interface TypePresenter
{
    /**
     * @param mixed $value
     * @return bool
     */
    public function supports($value): bool;

    /**
     * @param mixed $value
     * @return string
     */
    public function present($value): string;

    /**
     * @return int
     */
    public function getPriority(): int;
}
