<?php

namespace PhpSpec\Loader;

class Suite implements \Countable
{
    private $specs = array();

    public function addSpecification(Node\SpecificationNode $spec)
    {
        $this->specs[] = $spec;
        $spec->setSuite($this);
    }

    public function getSpecifications()
    {
        return $this->specs;
    }

    public function count()
    {
        return array_sum(array_map('count', $this->specs));
    }
}
