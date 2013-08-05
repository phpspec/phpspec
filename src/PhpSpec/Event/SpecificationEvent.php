<?php

namespace PhpSpec\Event;

use Symfony\Component\EventDispatcher\Event;

use PhpSpec\Loader\Node\SpecificationNode;

class SpecificationEvent extends Event implements EventInterface
{
    private $specification;
    private $time;
    private $result;

    public function __construct(SpecificationNode $specification, $time = null, $result = null)
    {
        $this->specification = $specification;
        $this->time          = $time;
        $this->result        = $result;
    }

    public function getSpecification()
    {
        return $this->specification;
    }

    public function getTitle()
    {
        return $this->specification->getTitle();
    }

    public function getSuite()
    {
        return $this->specification->getSuite();
    }

    public function getTime()
    {
        return $this->time;
    }

    public function getResult()
    {
        return $this->result;
    }
}
