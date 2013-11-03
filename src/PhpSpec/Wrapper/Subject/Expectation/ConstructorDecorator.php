<?php

namespace PhpSpec\Wrapper\Subject\Expectation;

class ConstructorDecorator extends Decorator implements ExpectationInterface
{

    public function match($alias, $subject, array $arguments = array())
    {
        return $this->getExpectation()->match($alias, $subject, $arguments);
    }
}
