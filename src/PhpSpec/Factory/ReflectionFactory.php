<?php

namespace PhpSpec\Factory;

class ReflectionFactory
{
    public function create($class)
    {
        return new \ReflectionClass($class);
    }
}

