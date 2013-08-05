<?php

namespace PhpSpec\Event;

use Symfony\Component\EventDispatcher\Event;
use PhpSpec\Loader\Node\ExampleNode;
use PhpSpec\Matcher\MatcherInterface;

class ExpectationEvent extends Event implements EventInterface
{
    private $example;
    private $matcher;

    public function __construct(ExampleNode $example, MatcherInterface $matcher)
    {
        $this->example = $example;
        $this->matcher = $matcher;
    }

    public function getMatcher()
    {
        return $this->matcher;
    }

    public function getExample()
    {
        return $this->example;
    }

    public function getSpecification()
    {
        return $this->example->getSpecification();
    }

    public function getSuite()
    {
        return $this->example->getSpecification()->getSuite();
    }
}
