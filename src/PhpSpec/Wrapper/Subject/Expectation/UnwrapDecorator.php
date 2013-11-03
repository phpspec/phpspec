<?php

namespace PhpSpec\Wrapper\Subject\Expectation;

use PhpSpec\Wrapper\Unwrapper;

class UnwrapDecorator extends Decorator implements ExpectationInterface
{
    private $unwrapper;

    public function __construct(ExpectationInterface $expectation, Unwrapper $unwrapper)
    {
        $this->setExpectation($expectation);
        $this->unwrapper = $unwrapper;
    }

    public function match($alias, $subject, array $arguments = array())
    {
        $arguments = $this->unwrapper->unwrapAll($arguments);

        return $this->getExpectation()->match($alias, $subject, $arguments);
    }
} 