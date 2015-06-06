<?php

namespace PhpSpec\CodeAnalysis;

use ReflectionMethod;
use ReflectionProperty;

final class MagicAwareAccessInspector implements AccessInspectorInterface
{
    /**
     * @param object $object
     * @param string $property
     *
     * @return bool
     */
    public function isPropertyReadable($object, $property)
    {
        if (!is_object($object)) {
            return false;
        }

        if (method_exists($object, '__get')) {
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
     * @param string $property
     *
     * @return bool
     */
    public function isPropertyWritable($object, $property)
    {
        if (!is_object($object)) {
            return false;
        }

        if (method_exists($object, '__set')) {
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
     *
     * @return bool
     */
    public function isMethodCallable($object, $method)
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
