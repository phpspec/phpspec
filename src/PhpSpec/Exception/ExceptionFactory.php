<?php

namespace PhpSpec\Exception;

use PhpSpec\Exception\Wrapper\SubjectException;
use PhpSpec\Formatter\Presenter\PresenterInterface;
use PhpSpec\Util\Instantiator;

class ExceptionFactory
{
    private $presenter;

    public function __construct(PresenterInterface $presenter)
    {
        $this->presenter = $presenter;
    }

    public function methodNotFound($classname, $method, array $arguments = array())
    {
        $instantiator = new Instantiator();
        $subject = $instantiator->instantiate($classname);
        $message = sprintf('Method %s not found.', $this->presenter->presentString($classname . '::' . $method));
        return new Fracture\MethodNotFoundException(
            $message, $subject, $this->presenter->presentString("{$classname}::{$method}"), $arguments
        );
    }

    public function classNotFound($classname)
    {
        $message = sprintf('Class %s does not exist.', $this->presenter->presentString($classname));
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
