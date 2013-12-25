<?php

namespace PhpSpec\Wrapper\Subject\Expectation;

class NegativeThrow extends DuringCall implements ThrowExpectation
{
    protected function runDuring($object, $method, array $arguments = array())
    {
        return $this->getMatcher()->negativeMatch('throw', $object, $this->getArguments())
            ->during($method, $arguments);
    }
}
