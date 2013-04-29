<?php

namespace PhpSpec\Exception;

use ReflectionFunctionAbstract;

class Exception extends \Exception
{
    private $cause;

    public function getCause()
    {
        return $this->cause;
    }

    public function setCause(ReflectionFunctionAbstract $cause)
    {
        $this->cause = $cause;
    }
}
