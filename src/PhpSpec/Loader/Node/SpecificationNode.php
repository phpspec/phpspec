<?php

namespace PhpSpec\Loader\Node;

use PhpSpec\Locator\ResourceInterface;

use ReflectionClass;

class SpecificationNode
{
    private $title;
    private $class;
    private $resource;
    private $examples = array();

    public function __construct($title, ReflectionClass $class, ResourceInterface $resource)
    {
        $this->title    = $title;
        $this->class    = $class;
        $this->resource = $resource;
    }

    public function getTitle()
    {
        return $this->title;
    }

    public function getClassReflection()
    {
        return $this->class;
    }

    public function getResource()
    {
        return $this->resource;
    }

    public function addExample(ExampleNode $example)
    {
        $this->examples[] = $example;
        $example->setSpecification($this);
    }

    public function getExamples()
    {
        return $this->examples;
    }
}
