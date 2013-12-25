<?php

namespace PhpSpec\Wrapper\Subject\Expectation;

class PositiveThrow extends DuringCall implements ThrowExpectation
{
    protected function runDuring($object, $method, array $arguments = array())
    {
        return $this->getMatcher()->positiveMatch('throw', $object, $this->getArguments())
            ->during($method, $arguments);
    }
}
