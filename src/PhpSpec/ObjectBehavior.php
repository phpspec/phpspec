<?php

namespace PhpSpec;

use PhpSpec\Matcher\MatchersProviderInterface;
use PhpSpec\Wrapper\WrapperInterface;
use PhpSpec\Wrapper\SubjectContainerInterface;
use PhpSpec\Wrapper\Subject;

use ArrayAccess;

class ObjectBehavior implements ArrayAccess,
                                MatchersProviderInterface,
                                SubjectContainerInterface,
                                WrapperInterface,
                                SpecificationInterface
{
    protected $object;

    public function getMatchers()
    {
        return array();
    }

    public function setSpecificationSubject(Subject $subject)
    {
        $this->object = $subject;
    }

    public function getWrappedObject()
    {
        return $this->object->getWrappedObject();
    }

    public function offsetExists($key)
    {
        return $this->object->offsetExists($key);
    }

    public function offsetGet($key)
    {
        return $this->object->offsetGet($key);
    }

    public function offsetSet($key, $value)
    {
        $this->object->offsetSet($key, $value);
    }

    public function offsetUnset($key)
    {
        return $this->object->offsetUnset($key);
    }

    public function __call($method, array $arguments = array())
    {
        return call_user_func_array(array($this->object, $method), $arguments);
    }

    public function __set($property, $value)
    {
        $this->object->$property = $value;
    }

    public function __get($property)
    {
        return $this->object->$property;
    }

    public function __invoke()
    {
        return call_user_func_array(array($this->object, '__invoke'), func_get_args());
    }
}
