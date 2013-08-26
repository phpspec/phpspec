<?php

namespace PhpSpec\Wrapper\Subject;

class ExpectationFactory
{
    private $example;
    private $dispatcher;
    private $matchers;

    public function __construct($example, $dispatcher, $matchers)
    {
        $this->example = $example;
        $this->dispatcher = $dispatcher;
        $this->matchers = $matchers;
    }

    public function create($subject)
    {
        return new Expectation($subject, $this->example, $this->dispatcher, $this->matchers);
    }
}
