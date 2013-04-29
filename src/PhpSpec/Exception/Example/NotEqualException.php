<?php

namespace PhpSpec\Exception\Example;

class NotEqualException extends FailureException
{
    private $expected;
    private $actual;

    public function __construct($message, $expected, $actual)
    {
        parent::__construct($message);

        $this->expected = $expected;
        $this->actual   = $actual;
    }

    public function getExpected()
    {
        return $this->expected;
    }

    public function getActual()
    {
        return $this->actual;
    }

    public function __toString()
    {
        return var_export(array($this->expected, $this->actual), true);
    }
}
