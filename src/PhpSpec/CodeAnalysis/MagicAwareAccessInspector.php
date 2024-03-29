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

namespace PhpSpec\CodeAnalysis;

final class MagicAwareAccessInspector implements AccessInspector
{
    public function __construct(
        private AccessInspector $accessInspector
    )
    {
    }

    public function isPropertyReadable(object $object, string $property): bool
    {
        return method_exists($object, '__get') || $this->accessInspector->isPropertyReadable($object, $property);
    }

    public function isPropertyWritable(object $object, string $property): bool
    {
        return method_exists($object, '__set') || $this->accessInspector->isPropertyWritable($object, $property);
    }

    public function isMethodCallable(object $object, string $method): bool
    {
        return method_exists($object, '__call') || $this->accessInspector->isMethodCallable($object, $method);
    }
}
