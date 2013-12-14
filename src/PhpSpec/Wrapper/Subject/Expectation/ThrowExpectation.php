<?php

namespace PhpSpec\Wrapper\Subject\Expectation;

interface ThrowExpectation
{
    public function during($method, array $arguments = array());
}
