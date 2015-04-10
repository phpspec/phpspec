<?php

namespace Phpspec\CodeAnalysis;

interface AccessInspectorInterface
{
    /**
     * @param object $object
     * @param string $property
     * @param bool $withValue
     * @return bool
     */
    public function isPropertyAccessible($object, $property, $withValue);

    /**
     * @param object $object
     * @param string $method
     * @return bool
     */
    public function isMethodAccessible($object, $method);
}
