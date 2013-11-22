<?php

namespace PhpSpec\Wrapper\Subject;

use PhpSpec\Exception\ExceptionFactory;
use PhpSpec\Loader\Node\ExampleNode;

use PhpSpec\Wrapper\Subject;
use PhpSpec\Wrapper\Wrapper;
use PhpSpec\Wrapper\Unwrapper;

use PhpSpec\Event\MethodCallEvent;

use Symfony\Component\EventDispatcher\EventDispatcherInterface as Dispatcher;

use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use ReflectionException;

class Caller
{
    private $wrappedObject;
    private $example;
    private $dispatcher;
    private $wrapper;
    private $exceptionFactory;

    public function __construct(WrappedObject $wrappedObject, ExampleNode $example, Dispatcher $dispatcher,
                                ExceptionFactory $exceptions, Wrapper $wrapper)
    {
        $this->wrappedObject    = $wrappedObject;
        $this->example          = $example;
        $this->dispatcher       = $dispatcher;
        $this->wrapper          = $wrapper;
        $this->exceptionFactory = $exceptions;
    }
    
    public function call($method, array $arguments = array())
    {
        if (null === $this->getWrappedObject()) {
            throw $this->callingMethodOnNonObject($method);
        }

        $subject   = $this->wrappedObject->getInstance();
        $unwrapper = new Unwrapper;
        $arguments = $unwrapper->unwrapAll($arguments);

        if ($this->isObjectMethodAccessible($method)) {
            return $this->invokeAndWrapMethodResult($subject, $method, $arguments);
        }

        throw $this->methodNotFound($method, $arguments);
    }

    public function set($property, $value = null)
    {
        if (null === $this->getWrappedObject()) {
            throw $this->settingPropertyOnNonObject($property);
        }

        $unwrapper = new Unwrapper;
        $value = $unwrapper->unwrapOne($value);

        if ($this->isObjectPropertyAccessible($property, true)) {
            return $this->getWrappedObject()->$property = $value;
        }

        throw $this->propertyNotFound($property);
    }

    public function get($property)
    {
        if ($this->lookingForConstants($property) && $this->constantDefined($property)) {
            return constant($this->wrappedObject->getClassName().'::'.$property);
        }

        if (null === $this->getWrappedObject()) {
            throw $this->accessingPropertyOnNonObject($property);
        }

        if ($this->isObjectPropertyAccessible($property)) {
            return $this->wrap($this->getWrappedObject()->$property);
        }

        throw $this->propertyNotFound($property);
    }

    public function getWrappedObject()
    {
        if ($this->wrappedObject->isInstantiated()) {
            return $this->wrappedObject->getInstance();
        }

        if (null === $this->wrappedObject->getClassName() || !is_string($this->wrappedObject->getClassName())) {
            return $this->wrappedObject->getInstance();
        }

        if (!class_exists($this->wrappedObject->getClassName())) {
            throw $this->classNotFound();
        }

        if (is_object($this->wrappedObject->getInstance())) {
            $this->wrappedObject->setInstantiated(true);
            $instance = $this->wrappedObject->getInstance();
        } else {
            $instance = $this->instantiateWrappedObject();
            $this->wrappedObject->setInstance($instance);
            $this->wrappedObject->setInstantiated(true);
        }

        return $instance;
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

    private function instantiateWrappedObject()
    {
        $reflection = new ReflectionClass($this->wrappedObject->getClassName());

        if (count($this->wrappedObject->getArguments())) {
            return $this->newInstanceWithArguments($reflection);
        }
        
        return $reflection->newInstance();
    }

    private function invokeAndWrapMethodResult($subject, $method, array $arguments = array())
    {
        $this->dispatcher->dispatch('beforeMethodCall',
            new MethodCallEvent($this->example, $subject, $method, $arguments)
        );

        $returnValue = call_user_func_array(array($subject, $method), $arguments);

        $this->dispatcher->dispatch('afterMethodCall',
            new MethodCallEvent($this->example, $subject, $method, $arguments)
        );

        return $this->wrap($returnValue);
    }

    private function wrap($value)
    {
        return $this->wrapper->wrap($value);
    }

    private function newInstanceWithArguments(ReflectionClass $reflection)
    {
        try {
            return $reflection->newInstanceArgs($this->wrappedObject->getArguments());
        } catch (ReflectionException $e) {
            if ($this->detectMissingConstructorMessage($e)) {
                throw $this->methodNotFound(
                    '__construct', $this->wrappedObject->getArguments()
                );
            }
            throw $e;
        }
    }

    private function detectMissingConstructorMessage(ReflectionException $exception)
    {
        return strpos(
            $exception->getMessage(), 'does not have a constructor'
        ) !== 0;
    }

    private function classNotFound()
    {
        return $this->exceptionFactory->classNotFound($this->wrappedObject->getClassName());
    }

    private function methodNotFound($method, array $arguments = array())
    {
        $className = $this->wrappedObject->getClassName();
        return $this->exceptionFactory->methodNotFound($className, $method, $arguments);
    }

    private function propertyNotFound($property)
    {
        return $this->exceptionFactory->propertyNotFound($this->getWrappedObject(), $property);
    }

    private function callingMethodOnNonObject($method)
    {
        return $this->exceptionFactory->callingMethodOnNonObject($method);
    }

    private function settingPropertyOnNonObject($property)
    {
        return $this->exceptionFactory->settingPropertyOnNonObject($property);
    }

    private function accessingPropertyOnNonObject($property)
    {
        return $this->exceptionFactory->gettingPropertyOnNonObject($property);
    }

    private function lookingForConstants($property)
    {
        return null !== $this->wrappedObject->getClassName() &&
            $property === strtoupper($property);
    }

    public function constantDefined($property)
    {
        return defined($this->wrappedObject->getClassName().'::'.$property);
    }
}
