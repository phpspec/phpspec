<?php

namespace PhpSpec\Wrapper\Subject\Expectation;

interface ExpectationInterface
{
    public function match($alias, $subject, array $arguments = array());
}
