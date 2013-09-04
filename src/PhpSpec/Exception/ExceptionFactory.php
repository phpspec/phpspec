<?php

namespace PhpSpec\Exception;

use PhpSpec\Formatter\Presenter\PresenterInterface;

class ExceptionFactory
{
    private $presenter;

    public function __construct(PresenterInterface $presenter)
    {
        $this->presenter = $presenter;
    }

    public function methodNotFound($message, $subject, $classname, $method, array $arguments = array())
    {
        return new Fracture\MethodNotFoundException(
            $message, $subject, $this->presenter->presentString("{$classname}::{$method}"), $arguments
        );
    }

    public function classNotFound($message, $classname)
    {
        return new Fracture\ClassNotFoundException($message, $classname);
    }
}
