<?php

namespace PhpSpec\Wrapper;

class DelayedCall
{
    private $callable;

    public function __construct($callable)
    {
        $this->callable = $callable;
    }

    public function __call($method, array $arguments)
    {
        return call_user_func($this->callable, $method, $arguments);
    }
}
