<?php

namespace PhpSpec\Event;

use Symfony\Component\EventDispatcher\Event;

use PhpSpec\Loader\Node\ExampleNode;

class ExampleEvent extends Event implements EventInterface
{
    const PASSED  = 0;
    const PENDING = 1;
    const FAILED  = 2;
    const BROKEN  = 3;

    private $example;
    private $time;
    private $result;
    private $exception;

    public function __construct(ExampleNode $example, $time = null, $result = null,
                                \Exception $exception = null)
    {
        $this->example   = $example;
        $this->time      = $time;
        $this->result    = $result;
        $this->exception = $exception;
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
        return $this->getSpecification()->getSuite();
    }

    public function getTitle()
    {
        return $this->example->getTitle();
    }

    public function getMessage()
    {
        return $this->exception->getMessage();
    }

    public function getBacktrace()
    {
        return $this->exception->getTrace();
    }

    public function getTime()
    {
        return $this->time;
    }

    public function getResult()
    {
        return $this->result;
    }

    public function getException()
    {
        return $this->exception;
    }
}
