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

final class NullTypePresenter implements TypePresenter
{
    public function supports(mixed $value): bool
    {
        return null === $value;
    }

    public function present(mixed $value): string
    {
        return 'null';
    }

    public function getPriority(): int
    {
        return 50;
    }
}
