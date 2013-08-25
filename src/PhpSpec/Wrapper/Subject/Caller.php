<?php

namespace PhpSpec\Wrapper\Subject;

use PhpSpec\Loader\Node\ExampleNode;
use PhpSpec\Runner\MatcherManager;
use PhpSpec\Wrapper\Subject;
use PhpSpec\Wrapper\Unwrapper;
use PhpSpec\Formatter\Presenter\PresenterInterface;
use PhpSpec\Exception\Exception;
use PhpSpec\Exception\Wrapper\SubjectException;
use PhpSpec\Exception\Fracture\ClassNotFoundException;
use PhpSpec\Exception\Fracture\MethodNotFoundException;
use PhpSpec\Exception\Fracture\PropertyNotFoundException;
use PhpSpec\Event\MethodCallEvent;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;

use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use ReflectionException;

class Caller
{
    private $matchers;
    private $unwrapper;
    private $presenter;
    private $dispatcher;
    private $example;
    private $wrappedObject;

    public function __construct(MatcherManager $matchers, Unwrapper $unwrapper,
                                PresenterInterface $presenter, EventDispatcherInterface $dispatcher,
                                ExampleNode $example, WrappedObject $wrappedObject)
    {
        $this->matchers      = $matchers;
        $this->unwrapper     = $unwrapper;
        $this->presenter     = $presenter;
        $this->dispatcher    = $dispatcher;
        $this->example       = $example;
        $this->wrappedObject = $wrappedObject;
    }
    
    public function callOnWrappedObject(Subject $subject, $method, array $arguments = array())
    {
        if (null === $this->getWrappedObject()) {
            throw callingMethodOnNonObject($method);
        }

        $subject   = $this->wrappedObject->getInstance();
        $arguments = $this->unwrapper->unwrapAll($arguments);

        if ($this->isObjectMethodAccessible($method)) {
            return $this->invokeAndWrapMethodResult($subject, $method, $arguments);
        }

        throw $this->methodNotFound($method, $arguments);
    }

    public function setToWrappedObject($property, $value = null)
    {
        if (null === $this->getWrappedObject()) {
            throw $this->settingPropertyOnNonObject($property);
        }

        $value = $this->unwrapper->unwrapAll($value);

        if ($this->isObjectPropertyAccessible($property, true)) {
            return $this->getWrappedObject()->$property = $value;
        }

        throw $this->propertyNotFound($property);
    }

    public function getFromWrappedObject($property)
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

        $instance = $this->instantiateWrappedObject();
        $this->wrappedObject->setInstance($instance);
        $this->wrappedObject->setInstantiated(true);

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
        return new Subject($value, $this->matchers, $this->unwrapper, $this->presenter, $this->dispatcher, $this->example);
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
        return new ClassNotFoundException(sprintf(
            'Class %s does not exist.', $this->presenter->presentString($this->wrappedObject->getClassName())
        ), $this->wrappedObject->getClassName());
    }

    private function methodNotfound($method, array $arguments = array())
    {
        $className = $this->wrappedObject->getClassName();
        return new MethodNotFoundException(sprintf(
            'Method %s not found.',
            $this->presenter->presentString($this->wrappedObject->getClassName().'::' . $method)
        ), new $className , $method, $arguments);
    }

    private function propertyNotFound($property)
    {
        return new PropertyNotFoundException(sprintf(
            'Property %s not found.',
            $this->presenter->presentString(get_class($this->getWrappedObject()).'::'.$property)
        ), $this->getWrappedObject(), $property);
    }

    private function callingMethodOnNonObject($method)
    {
        return new SubjectException(sprintf(
            'Call to a member function %s on a non-object.',
            $this->presenter->presentString($method.'()')
        ));
    }

    private function settingPropertyOnNonObject($property)
    {
        return new SubjectException(sprintf(
            'Setting property %s on a non-object.',
            $this->presenter->presentString($property)
        ));
    }

    private function accessingPropertyOnNonObject($property)
    {
        return new SubjectException(sprintf(
                'Getting property %s from a non-object.',
                $this->presenter->presentString($property)
        ));
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
