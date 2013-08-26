<?php

namespace PhpSpec\Wrapper\Subject;

use PhpSpec\Formatter\Presenter\PresenterInterface;
use PhpSpec\Wrapper\Unwrapper;
use PhpSpec\Exception\Wrapper\SubjectException;

class WrappedObject
{
    private $instance;
    private $presenter;
    private $unwrapper;
    private $classname;
    private $arguments = array();
    private $isInstantiated = false;

    public function __construct($instance, PresenterInterface $presenter)
    {
        $this->instance = $instance;
        $this->presenter = $presenter;
    }

    public function beAnInstanceOf($classname, array $arguments = array())
    {
        if (!is_string($classname)) {
            throw new SubjectException(sprintf(
                'Behavior subject classname should be a string, %s given.',
                $this->presenter->presentValue($classname)
            ));
        }

        $this->classname      = $classname;
        $unwrapper            = new Unwrapper;
        $this->arguments      = $unwrapper->unwrapAll($arguments);
        $this->isInstantiated = false;
    }
    
    public function beConstructedWith($args)
    {
        if (null === $this->classname) {
            throw new SubjectException(sprintf(
                'You can not set object arguments. Behavior subject is %s.',
                $this->presenter->presentValue(null)
            ));
        }

        $this->beAnInstanceOf($this->classname, $args);
    }

    public function isInstantiated()
    {
        return $this->isInstantiated;
    }

    public function setInstantiated($instantiated)
    {
        $this->isInstantiated = $instantiated;
    }

    public function getClassName()
    {
        return $this->classname;
    }

    public function setClassName($classname)
    {
        $this->classname = $classname;
    }

    public function getArguments()
    {
        return $this->arguments;
    }

    public function getInstance()
    {
        return $this->instance;
    }

    public function setInstance($instance)
    {
        $this->instance = $instance;
    }
}
