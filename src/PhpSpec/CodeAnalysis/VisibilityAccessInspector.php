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

final class VisibilityAccessInspector implements AccessInspectorInterface
{
    /**
     * @param object $object
     * @param string $property
     *
     * @return bool
     */
    public function isPropertyReadable($object, $property)
    {
        return $this->isExistingPublicProperty($object, $property);
    }

    /**
     * @param object $object
     * @param string $property
     *
     * @return bool
     */
    public function isPropertyWritable($object, $property)
    {
        return $this->isExistingPublicProperty($object, $property);
    }

    /**
     * @param object $object
     * @param string $property
     *
     * @return bool
     */
    private function isExistingPublicProperty($object, $property)
    {
        if (!property_exists($object, $property)) {
            return false;
        }

        $propertyReflection = new ReflectionProperty($object, $property);

        return $propertyReflection->isPublic();
    }

    /**
     * @param object $object
     * @param string $method
     *
     * @return bool
     */
    public function isMethodCallable($object, $method)
    {
        return $this->isExistingPublicMethod($object, $method);
    }

    /**
     * @param object $object
     * @param string $method
     *
     * @return bool
     */
    private function isExistingPublicMethod($object, $method)
    {
        if (!method_exists($object, $method)) {
            return false;
        }

        $methodReflection = new ReflectionMethod($object, $method);

        return $methodReflection->isPublic();
    }
}
