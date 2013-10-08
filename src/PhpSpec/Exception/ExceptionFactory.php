<?php

namespace PhpSpec\Exception;

use PhpSpec\Exception\Wrapper\SubjectException;
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
        return new Fracture\ClassNotFoundException($message, $this->presenter->presentString($classname));
    }

    public function propertyNotFound($message, $subject, $property)
    {
        return new Fracture\PropertyNotFoundException($message, $subject, $this->presenter->presentString($property));
    }

    public function callingMethodOnNonObject($method)
    {
        return new SubjectException(sprintf(
            'Call to a member function %s on a non-object.',
            $this->presenter->presentString($method.'()')
        ));
    }

    public function settingPropertyOnNonObject($property)
    {
        return new SubjectException(sprintf(
            'Setting property %s on a non-object.',
            $this->presenter->presentString($property)
        ));
    }

    public function gettingPropertyOnNonObject($property)
    {
        return new SubjectException(sprintf(
            'Getting property %s on a non-object.',
            $this->presenter->presentString($property)
        ));
    }
}
