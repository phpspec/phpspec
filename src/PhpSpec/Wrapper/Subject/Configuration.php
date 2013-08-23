<?php

namespace PhpSpec\Wrapper\Subject;

use PhpSpec\Formatter\Presenter\PresenterInterface;
use PhpSpec\Wrapper\Subject;
use PhpSpec\Wrapper\Unwrapper;
use PhpSpec\Exception\Wrapper\SubjectException;

class Configuration
{
    private $subject;
    private $presenter;
    private $unwrapper;
    private $classname;
    private $arguments = array();
    private $isInstantiated = true;

    public function __construct(Subject $subject, PresenterInterface $presenter, Unwrapper $unwrapper)
    {
        $this->subject = $subject;
        $this->presenter = $presenter;
        $this->unwrapper = $unwrapper;
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
        $this->arguments      = $this->unwrapper->unwrapAll($arguments);
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

    public function getArguments()
    {
        return $this->arguments;
    }
}
