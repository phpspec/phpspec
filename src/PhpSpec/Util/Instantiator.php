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

namespace PhpSpec\Util;

use PhpSpec\Exception\Fracture\ClassNotFoundException;

use ReflectionClass;
use ReflectionProperty;

/**
 * Class Instantiator
 * @package PhpSpec\Util
 */
class Instantiator
{
    /**
     * @param string $className
     *
     * @return object
     */
    public function instantiate($className)
    {
        return unserialize($this->createSerializedObject($className));
    }

    /**
     * @param string $className
     *
     * @return string
     *
     * @throws \PhpSpec\Exception\Fracture\ClassNotFoundException
     */
    private function createSerializedObject($className)
    {
        if (!class_exists($className)) {
            throw new ClassNotFoundException("Class $className does not exist.", $className);
        }

        $reflection = new ReflectionClass($className);
        $properties = $reflection->getProperties();

        return "O:" . strlen($className) . ":\"$className\":" . count($properties) .
            ':{' . $this->serializeProperties($reflection, $properties) ."}";
    }

    /**
     * @param ReflectionClass      $reflection
     * @param ReflectionProperty[] $properties
     *
     * @return string
     */
    private function serializeProperties(ReflectionClass $reflection, array $properties)
    {
        $serializedProperties = '';

        foreach ($properties as $property) {
            $serializedProperties .= $this->serializePropertyName($reflection, $property);
            $serializedProperties .= $this->serializePropertyValue($reflection, $property);
        }

        return $serializedProperties;
    }

    /**
     * @param ReflectionClass    $class
     * @param ReflectionProperty $property
     *
     * @return string
     */
    private function serializePropertyName(ReflectionClass $class, ReflectionProperty $property)
    {
        $propertyName = $property->getName();

        if ($property->isProtected()) {
            $propertyName = chr(0) . '*' . chr(0) . $propertyName;
        } elseif ($property->isPrivate()) {
            $propertyName = chr(0) . $class->getName() . chr(0) . $propertyName;
        }

        return serialize($propertyName);
    }

    /**
     * @param ReflectionClass    $class
     * @param ReflectionProperty $property
     *
     * @return string
     */
    private function serializePropertyValue(ReflectionClass $class, ReflectionProperty $property)
    {
        $defaults = $class->getDefaultProperties();

        if (array_key_exists($property->getName(), $defaults)) {
            return serialize($defaults[$property->getName()]);
        }

        return serialize(null);
    }
}
