<?php

namespace PhpSpec\Wrapper;

use PhpSpec\Wrapper\Subject\WrappedObject;
use PhpSpec\Wrapper\Subject\Caller;
use PhpSpec\Wrapper\Subject\SubjectWithArrayAccess;
use PhpSpec\Wrapper\Subject\Expectation;
use PhpSpec\Wrapper\Subject\ExpectationFactory;

use ArrayAccess;

class Subject implements ArrayAccess, WrapperInterface
{
    private $subject;
    private $wrappedObject;
    private $caller;
    private $arrayAccess;
    private $wrapper;
    private $expectationFactory;

    public function __construct($subject, Wrapper $wrapper, WrappedObject $wrappedObject, Caller $caller,
                                SubjectWithArrayAccess $arrayAccess, ExpectationFactory $expectationFactory)
    {
        $this->subject            = $subject;
        $this->wrapper            = $wrapper;
        $this->wrappedObject      = $wrappedObject;
        $this->caller             = $caller;
        $this->arrayAccess        = $arrayAccess;
        $this->expectationFactory = $expectationFactory;
    }

    public function beAnInstanceOf($className, array $arguments = array())
    {
        $this->wrappedObject->beAnInstanceOf($className, $arguments);
    }

    public function beConstructedWith()
    {
        $this->wrappedObject->beConstructedWith(func_get_args());
    }

    public function should($name = null, array $arguments = array())
    {
        return $this->createExpectation()->positive($name, $arguments);
    }

    public function shouldNot($name = null, array $arguments = array())
    {
        return $this->createExpectation()->negative($name, $arguments);
    }

    public function callOnWrappedObject($method, array $arguments = array())
    {
        return $this->caller->call($method, $arguments);
    }

    public function setToWrappedObject($property, $value = null)
    {
        return $this->caller->set($property, $value);
    }

    public function getFromWrappedObject($property)
    {
        return $this->caller->get($property);
    }

    public function getWrappedObject()
    {
        if ($this->subject) {
            return $this->subject;
        }

        return $this->subject = $this->caller->getWrappedObject();
    }

    public function offsetExists($key)
    {
        return $this->arrayAccess->offSetExists($key);
    }

    public function offsetGet($key)
    {
        return $this->wrap($this->arrayAccess->offsetGet($key));
    }

    public function offsetSet($key, $value)
    {
        return $this->arrayAccess->offsetSet($key, $value);
    }

    public function offsetUnset($key)
    {
        $this->arrayAccess->offsetUnset($key);;
    }

    public function __call($method, array $arguments = array())
    {
        if (0 === strpos($method, 'shouldNot')) {
            return $this->shouldNot(lcfirst(substr($method, 9)), $arguments);
        }

        if (0 === strpos($method, 'should')) {
            return $this->should(lcfirst(substr($method, 6)), $arguments);
        }

        return $this->callOnWrappedObject($method, $arguments);
    }

    public function __invoke()
    {
        return $this->callOnWrappedObject('__invoke', func_get_args());
    }

    public function __set($property, $value = null)
    {
        return $this->setToWrappedObject($property, $value);
    }

    public function __get($property)
    {
        return $this->getFromWrappedObject($property);
    }

    private function wrap($value)
    {
        return $this->wrapper->wrap($value);
    }

    public function createExpectation()
    {
        if ($this->subject === null) {
            $unwrapper = new Unwrapper;
            $this->subject = $unwrapper->unwrapOne($this);
        }

        return $this->expectationFactory->create($this->subject);
    }
}
