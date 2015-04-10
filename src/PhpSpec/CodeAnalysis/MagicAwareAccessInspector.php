<?php

namespace PhpSpec\CodeAnalysis;

use ReflectionMethod;
use ReflectionProperty;

final class MagicAwareAccessInspector implements AccessInspectorInterface
{
    /**
     * @param object $object
     * @param string $property
     * @param bool $withValue
     * @return bool
     */
    public function isPropertyAccessible($object, $property, $withValue = false)
    {
        if (!is_object($object)) {
            return false;
        }

        if (method_exists($object, $withValue ? '__set' : '__get')) {
            return true;
        }

        if (!property_exists($object, $property)) {
            return false;
        }

        $propertyReflection = new ReflectionProperty($object, $property);
        return $propertyReflection->isPublic();
    }

    /**
     * @param object $object
     * @param string $method
     * @return bool
     */
    public function isMethodAccessible($object, $method)
    {
        if (!is_object($object)) {
            return false;
        }

        if (method_exists($object, '__call')) {
            return true;
        }

        if (!method_exists($object, $method)) {
            return false;
        }

        $methodReflection = new ReflectionMethod($object, $method);
        return $methodReflection->isPublic();
    }
}
