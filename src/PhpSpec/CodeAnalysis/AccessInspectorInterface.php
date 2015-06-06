<?php

namespace Phpspec\CodeAnalysis;

interface AccessInspectorInterface
{
    /**
     * @param object $object
     * @param string $property
     *
     * @return bool
     */
    public function isPropertyReadable($object, $property);

    /**
     * @param object $object
     * @param string $property
     *
     * @return bool
     */
    public function isPropertyWritable($object, $property);

    /**
     * @param object $object
     * @param string $method
     *
     * @return bool
     */
    public function isMethodCallable($object, $method);
}
