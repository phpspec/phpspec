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

use ReflectionMethod;
use ReflectionProperty;

final class VisibilityAccessInspector implements AccessInspector
{
    public function isPropertyReadable(object $object, string $property): bool
    {
        return $this->isExistingPublicProperty($object, $property);
    }

    public function isPropertyWritable(object $object, string $property): bool
    {
        return $this->isExistingPublicProperty($object, $property);
    }

    private function isExistingPublicProperty(object $object, string $property): bool
    {
        if (!property_exists($object, $property)) {
            return false;
        }

        $propertyReflection = new ReflectionProperty($object, $property);

        return $propertyReflection->isPublic();
    }

    public function isMethodCallable(object $object, string $method): bool
    {
        return $this->isExistingPublicMethod($object, $method);
    }

    private function isExistingPublicMethod(object $object, string $method): bool
    {
        if (!method_exists($object, $method)) {
            return false;
        }

        $methodReflection = new ReflectionMethod($object, $method);

        return $methodReflection->isPublic();
    }
}
