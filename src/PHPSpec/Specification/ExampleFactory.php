<?php

namespace PHPSpec\Specification;

class ExampleFactory
{
    public function create(ExampleGroup $exampleGroup, \ReflectionMethod $example)
    {
        return new Example($exampleGroup, $example);
    }
}