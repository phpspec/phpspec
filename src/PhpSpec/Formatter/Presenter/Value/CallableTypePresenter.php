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

use PhpSpec\Formatter\Presenter\Presenter;

final class CallableTypePresenter implements TypePresenter
{
    public function __construct(private Presenter $presenter)
    {
    }

    public function supports(mixed $value): bool
    {
        return is_callable($value);
    }

    public function present(mixed $value): string
    {
        if (\is_array($value)) {
            $type = \is_object($value[0]) ? $this->presenter->presentValue($value[0]) : $value[0];
            return sprintf('%s::%s()', $type, $value[1]);
        }

        if ($value instanceof \Closure) {
            return '[closure]';
        }

        if (\is_object($value)) {
            return sprintf('[obj:%s]', $value::class);
        }

        return sprintf('[%s()]', $value);
    }
    
    public function getPriority(): int
    {
        return 70;
    }
}
