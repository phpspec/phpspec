<?php

namespace PhpSpec\Loader\Node;

use ReflectionFunctionAbstract;

class ExampleNode
{
    private $title;
    private $function;
    private $specification;
    private $isPending = false;

    public function __construct($title, ReflectionFunctionAbstract $function)
    {
        $this->title    = $title;
        $this->function = $function;
    }

    public function getTitle()
    {
        return $this->title;
    }

    public function markAsPending($isPending = true)
    {
        $this->isPending = $isPending;
    }

    public function isPending()
    {
        return $this->isPending;
    }

    public function getFunctionReflection()
    {
        return $this->function;
    }

    public function setSpecification(SpecificationNode $specification)
    {
        $this->specification = $specification;
    }

    public function getSpecification()
    {
        return $this->specification;
    }
}
