<?php

namespace PhpSpec\Wrapper\Subject\Expectation;

abstract class Decorator implements ExpectationInterface
{
    private $expectation;

    public function __construct(ExpectationInterface $expectation)
    {
        $this->expectation = $expectation;
    }

    public function getExpectation()
    {
        $expectation = $this->expectation;
        while ($expectation instanceof Decorator) {
            $expectation = $expectation->getExpectation();
        }
        return $expectation;
    }

    protected function setExpectation($expectation)
    {
        $this->expectation = $expectation;
    }
}
