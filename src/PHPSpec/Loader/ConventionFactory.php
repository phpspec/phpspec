<?php

namespace PHPSpec\Loader;

class ConventionFactory
{
    public function create($spec)
    {
        return new ApplyConvention($spec);
    }
}