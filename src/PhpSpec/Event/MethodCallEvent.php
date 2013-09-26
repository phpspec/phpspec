<?php

namespace PhpSpec\Event;

use PhpSpec\Loader\Node\ExampleNode;
use PhpSpec\Wrapper\Subject;
use Symfony\Component\EventDispatcher\Event;

class MethodCallEvent extends Event implements EventInterface
{
    private $example;
    private $subject;
    private $method;
    private $arguments;
    private $returnValue;

    public function __construct(ExampleNode $example, $subject, $method, $arguments, $returnValue = null)
    {
        $this->example = $example;
        $this->subject = $subject;
        $this->method = $method;
        $this->arguments = $arguments;
        $this->returnValue = $returnValue;
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

    public function getSubject()
    {
        return $this->subject;
    }

    public function getMethod()
    {
        return $this->method;
    }

    public function getArguments()
    {
        return $this->arguments;
    }

    public function getReturnValue()
    {
        return $this->returnValue;
    }
}
