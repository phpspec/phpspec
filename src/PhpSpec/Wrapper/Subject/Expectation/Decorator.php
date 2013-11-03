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
        return $this->expectation;
    }

    protected function setExpectation($expectation)
    {
        $this->expectation = $expectation;
    }

    public function getNestedExpectation()
    {
        $expectation = $this->getExpectation();
        while ($expectation instanceof Decorator) {
            $expectation = $expectation->getExpectation();
        }

        return $expectation;
    }
}
