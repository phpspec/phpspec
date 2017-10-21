<?php

namespace PhpSpec\CodeGenerator\Generator\Argument;

class Argument
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var string|\ReflectionClass
     */
    private $type;

    /**
     * @var bool
     */
    private $hasDefaultValue;

    /**
     * @var mixed
     */
    private $defaultValue;

    public function __construct(string $name, $type)
    {
        $this->name = $name;
        $this->type = $type;
        $this->hasDefaultValue = false;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return string|\ReflectionClass
     */
    public function getType()
    {
        return $this->type;
    }

    public function hasDefaultValue(): bool
    {
        return $this->hasDefaultValue;
    }

    public function setDefaultValue($defaultValue)
    {
        $this->defaultValue = $defaultValue;
        $this->hasDefaultValue = true;
    }

    public function getDefaultValue()
    {
        return $this->defaultValue;
    }
}
