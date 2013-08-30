<?php

namespace PhpSpec\Util;

use ReflectionClass;
use ReflectionProperty;

class Instantiator
{
    public function instantiate($className)
    {
        return unserialize($this->createSerializedObject($className));
    }

    private function createSerializedObject($className)
    {
        $reflection = new ReflectionClass($className);
        $properties = $reflection->getProperties();

        return "O:" . strlen($className) . ":\"$className\":" . count($properties) .
            ':{' . $this->serializeProperties($reflection, $properties) ."}";
    }

    private function serializeProperties(ReflectionClass $reflection, array $properties)
    {
        $serializedProperties = '';

        foreach ($properties as $property) {
            $serializedProperties .= $this->serializePropertyName($property);
            $serializedProperties .= $this->serializePropertyValue($reflection, $property);
        }

        return $serializedProperties;
    }

    private function serializePropertyName(ReflectionProperty $property)
    {
        $propertyName = $property->getName();

        if ($property->isProtected()) {
            $propertyName = chr(0) . '*' . chr(0) . $propertyName;
        } elseif ($property->isPrivate()) {
            $propertyName = chr(0) . $class . chr(0) . $propertyName;
        }

        return serialize($propertyName);
    }
    
    private function serializePropertyValue(ReflectionClass $class, ReflectionProperty $property)
    {
        if (array_key_exists($property->getName(), $class->getDefaultProperties())) {
            return serialize($defaults[$property->getName()]);
        }

        return serialize(null);
    }
}
