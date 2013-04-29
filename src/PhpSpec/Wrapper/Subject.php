<?php

namespace PhpSpec\Wrapper;

use PhpSpec\Runner\MatcherManager;
use PhpSpec\Formatter\Presenter\PresenterInterface;

use PhpSpec\Exception\Exception;
use PhpSpec\Exception\Wrapper\SubjectException;
use PhpSpec\Exception\Fracture\ClassNotFoundException;
use PhpSpec\Exception\Fracture\MethodNotFoundException;
use PhpSpec\Exception\Fracture\PropertyNotFoundException;
use PhpSpec\Exception\Fracture\InterfaceNotImplementedException;

use ArrayAccess;

use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

class Subject implements ArrayAccess, WrapperInterface
{
    private $classname;
    private $arguments = array();
    private $matchers;
    private $unwrapper;
    private $presenter;
    private $subject;
    private $isInstantiated = false;

    public function __construct($subject, MatcherManager $matchers, Unwrapper $unwrapper,
                                PresenterInterface $presenter)
    {
        $this->subject        = $subject;
        $this->matchers       = $matchers;
        $this->unwrapper      = $unwrapper;
        $this->presenter      = $presenter;
        $this->isInstantiated = true;
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

    public function beConstructedWith()
    {
        if (null === $this->classname) {
            throw new SubjectException(sprintf(
                'You can not set object arguments. Behavior subject is %s.',
                $this->presenter->presentValue(null)
            ));
        }

        $this->beAnInstanceOf($this->classname, func_get_args());
    }

    public function should($name = null, array $arguments = array())
    {
        if (null === $name) {
            return new DelayedCall(array($this, __METHOD__));
        }

        $subject   = $this->unwrapper->unwrapOne($this);
        $arguments = $this->unwrapper->unwrapAll($arguments);
        $matcher   = $this->matchers->find($name, $subject, $arguments);

        return $matcher->positiveMatch($name, $subject, $arguments);
    }

    public function shouldNot($name = null, array $arguments = array())
    {
        if (null === $name) {
            return new DelayedCall(array($this, __METHOD__));
        }

        $subject   = $this->unwrapper->unwrapOne($this);
        $arguments = $this->unwrapper->unwrapAll($arguments);
        $matcher   = $this->matchers->find($name, $subject, $arguments);

        return $matcher->negativeMatch($name, $subject, $arguments);
    }

    public function callOnWrappedObject($method, array $arguments = array())
    {
        if (null === $this->getWrappedObject()) {
            throw new SubjectException(sprintf(
                'Call to a member function %s on a non-object.',
                $this->presenter->presentString($method.'()')
            ));
        }

        // resolve arguments
        $subject   = $this->unwrapper->unwrapOne($this);
        $arguments = $this->unwrapper->unwrapAll($arguments);

        // if subject is an instance with provided method - call it and stub the result
        if ($this->isObjectMethodAccessible($method)) {
            $returnValue = call_user_func_array(array($subject, $method), $arguments);

            return new static($returnValue, $this->matchers, $this->unwrapper, $this->presenter);
        }

        throw new MethodNotFoundException(sprintf(
            'Method %s not found.',
            $this->presenter->presentString(get_class($subject).'::'.$method.'()')
        ), $subject, $method, $arguments);
    }

    public function setToWrappedObject($property, $value = null)
    {
        if (null === $this->getWrappedObject()) {
            throw new SubjectException(sprintf(
                'Setting property %s on a non-object.',
                $this->presenter->presentString($property)
            ));
        }

        $value = $this->unwrapper->unwrapAll($value);

        if ($this->isObjectPropertyAccessible($property, true)) {
            return $this->getWrappedObject()->$property = $value;
        }

        throw new PropertyNotFoundException(sprintf(
            'Property %s not found.',
            $this->presenter->presentString(get_class($this->getWrappedObject()).'::'.$property)
        ), $this->getWrappedObject(), $property);
    }

    public function getFromWrappedObject($property)
    {
        // transform camel-cased properties to constant lookups
        if (null !== $this->classname && $property === strtoupper($property)) {
            if (defined($this->classname.'::'.$property)) {
                return constant($this->classname.'::'.$property);
            }
        }

        if (null === $this->getWrappedObject()) {
            throw new SubjectException(sprintf(
                'Getting property %s from a non-object.',
                $this->presentString($property)
            ));
        }

        if ($this->isObjectPropertyAccessible($property)) {
            $returnValue = $this->getWrappedObject()->$property;

            return new static($returnValue, $this->matchers, $this->unwrapper, $this->presenter);
        }

        throw new PropertyNotFoundException(sprintf(
            'Property %s not found.',
            $this->presenter->presentString(get_class($this->getWrappedObject()).'::'.$property)
        ), $this->getWrappedObject(), $property);
    }

    public function getWrappedObject()
    {
        if ($this->isInstantiated) {
            return $this->subject;
        }

        if (null === $this->classname || !is_string($this->classname)) {
            throw new Exception(sprintf(
                'Instantiator expects class name, got %s.',
                $this->presenter->presentValue($this->classname)
            ));
        }

        if (!class_exists($this->classname)) {
            throw new ClassNotFoundException(sprintf(
                'Class %s does not exists.', $this->presenter->presentString($this->classname)
            ), $this->classname);
        }

        $this->isInstantiated = true;

        $reflection = new ReflectionClass($this->classname);

        if (!empty($this->arguments)) {
            return $this->subject = $reflection->newInstanceArgs($this->arguments);
        }

        return $this->subject = $reflection->newInstance();
    }

    public function offsetExists($key)
    {
        $subject = $this->getWrappedObject();
        $key     = $this->unwrapper->unwrapOne($key);

        if (is_object($subject) && !($subject instanceof ArrayAccess)) {
            throw new InterfaceNotImplementedException(
                sprintf('%s does not implement %s interface, but should.',
                    $this->presenter->presentValue($this->getWrappedObject()),
                    $this->presenter->presentString('ArrayAccess')
                ),
                $this->getWrappedObject(),
                'ArrayAccess'
            );
        } elseif (!($subject instanceof ArrayAccess) && !is_array($subject)) {
            throw new SubjectException(sprintf(
                'Can not use %s as array.', $this->presenter->presentValue($subject)
            ));
        }

        return isset($subject[$key]);
    }

    public function offsetGet($key)
    {
        $subject = $this->getWrappedObject();
        $key     = $this->unwrapper->unwrapOne($key);

        if (is_object($subject) && !($subject instanceof ArrayAccess)) {
            throw new InterfaceNotImplementedException(
                sprintf('%s does not implement %s interface, but should.',
                    $this->presenter->presentValue($this->getWrappedObject()),
                    $this->presenter->presentString('ArrayAccess')
                ),
                $this->getWrappedObject(),
                'ArrayAccess'
            );
        } elseif (!($subject instanceof ArrayAccess) && !is_array($subject)) {
            throw new SubjectException(sprintf(
                'Can not use %s as array.', $this->presenter->presentValue($subject)
            ));
        }

        return new static($subject[$key], $this->matchers, $this->unwrapper, $this->presenter);
    }

    public function offsetSet($key, $value)
    {
        $subject = $this->getWrappedObject();
        $key     = $this->unwrapper->unwrapOne($key);
        $value   = $this->unwrapper->unwrapOne($value);

        if (is_object($subject) && !($subject instanceof ArrayAccess)) {
            throw new InterfaceNotImplementedException(
                sprintf('%s does not implement %s interface, but should.',
                    $this->presenter->presentValue($this->getWrappedObject()),
                    $this->presenter->presentString('ArrayAccess')
                ),
                $this->getWrappedObject(),
                'ArrayAccess'
            );
        } elseif (!($subject instanceof ArrayAccess) && !is_array($subject)) {
            throw new SubjectException(sprintf(
                'Can not use %s as array.', $this->presenter->presentValue($subject)
            ));
        }

        $subject[$key] = $value;
    }

    public function offsetUnset($key)
    {
        $subject = $this->getWrappedObject();
        $key     = $this->unwrapper->unwrapOne($key);

        if (is_object($subject) && !($subject instanceof ArrayAccess)) {
            throw new InterfaceNotImplementedException(
                sprintf('%s does not implement %s interface, but should.',
                    $this->presenter->presentValue($this->getWrappedObject()),
                    $this->presenter->presentString('ArrayAccess')
                ),
                $this->getWrappedObject(),
                'ArrayAccess'
            );
        } elseif (!($subject instanceof ArrayAccess) && !is_array($subject)) {
            throw new SubjectException(sprintf(
                'Can not use %s as array.', $this->presenter->presentValue($subject)
            ));
        }

        unset($subject[$key]);
    }

    public function __call($method, array $arguments = array())
    {
        // if user calls function with should prefix - call matcher
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

    private function isObjectMethodAccessible($method)
    {
        if (!is_object($this->getWrappedObject())) {
            return false;
        }

        if (method_exists($this->getWrappedObject(), '__call')) {
            return true;
        }

        if (!method_exists($this->getWrappedObject(), $method)) {
            return false;
        }

        $methodReflection = new ReflectionMethod($this->getWrappedObject(), $method);

        return $methodReflection->isPublic();
    }

    private function isObjectPropertyAccessible($property, $withValue = false)
    {
        if (!is_object($this->getWrappedObject())) {
            return false;
        }

        if (method_exists($this->getWrappedObject(), $withValue ? '__set' : '__get')) {
            return true;
        }

        if (!property_exists($this->getWrappedObject(), $property)) {
            return false;
        }

        $propertyReflection = new ReflectionProperty($this->getWrappedObject(), $property);

        return $propertyReflection->isPublic();
    }
}
