<?php

namespace PhpSpec\Event;

use Symfony\Component\EventDispatcher\Event;
use PhpSpec\Loader\Node\ExampleNode;
use PhpSpec\Matcher\MatcherInterface;

class ExpectationEvent extends Event implements EventInterface
{
    const PASSED  = 0;
    const FAILED  = 1;
    const BROKEN  = 2;

    private $example;
    private $matcher;
    private $subject;
    private $method;
    private $arguments;
    private $result;
    private $exception;

    public function __construct(ExampleNode $example, MatcherInterface $matcher, $subject,
                                $method, $arguments, $result = null, $exception = null)
    {
        $this->example = $example;
        $this->matcher = $matcher;
        $this->subject = $subject;
        $this->method = $method;
        $this->arguments = $arguments;
        $this->result = $result;
        $this->exception = $exception;
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

    public function getException()
    {
        return $this->exception;
    }

    public function getResult()
    {
        return $this->result;
    }
}
