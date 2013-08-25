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

class Caller
{
    private $matchers;
    private $unwrapper;
    private $presenter;
    private $dispatcher;
    private $example;
    private $configuration;

    public function __construct(MatcherManager $matchers, Unwrapper $unwrapper,
                                PresenterInterface $presenter, EventDispatcherInterface $dispatcher,
                                ExampleNode $example, Configuration $configuration)
    {
        $this->matchers      = $matchers;
        $this->unwrapper     = $unwrapper;
        $this->presenter     = $presenter;
        $this->dispatcher    = $dispatcher;
        $this->example       = $example;
        $this->configuration = $configuration;
    }
    
    public function callOnWrappedObject(Subject $subject, $method, array $arguments = array())
    {
        if (null === $this->getWrappedObject()) {
            throw new SubjectException(sprintf(
                'Call to a member function %s on a non-object.',
                $this->presenter->presentString($method.'()')
            ));
        }

        // resolve arguments
        $subject   = $this->configuration->getInstance();
        $arguments = $this->unwrapper->unwrapAll($arguments);

        // if subject is an instance with provided method - call it and stub the result
        if ($this->isObjectMethodAccessible($method)) {

            $this->dispatcher->dispatch('beforeMethodCall',
                new MethodCallEvent($this->example, $subject, $method, $arguments)
            );

            $returnValue = call_user_func_array(array($subject, $method), $arguments);

            $this->dispatcher->dispatch('afterMethodCall',
                new MethodCallEvent($this->example, $subject, $method, $arguments)
            );

            return new Subject($returnValue, $this->matchers, $this->unwrapper, $this->presenter, $this->dispatcher, $this->example);
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
        if (null !== $this->configuration->getClassName() && $property === strtoupper($property)) {
            if (defined($this->configuration->getClassName().'::'.$property)) {
                return constant($this->configuration->getClassName().'::'.$property);
            }
        }

        if (null === $this->getWrappedObject()) {
            throw new SubjectException(sprintf(
                'Getting property %s from a non-object.',
                $this->presenter->presentString($property)
            ));
        }

        if ($this->isObjectPropertyAccessible($property)) {
            $returnValue = $this->getWrappedObject()->$property;

            return new Subject($returnValue, $this->matchers, $this->unwrapper, $this->presenter, $this->dispatcher, $this->example);
        }

        throw new PropertyNotFoundException(sprintf(
            'Property %s not found.',
            $this->presenter->presentString(get_class($this->getWrappedObject()).'::'.$property)
        ), $this->getWrappedObject(), $property);
    }

    public function getWrappedObject()
    {
        if ($this->configuration->isInstantiated()) {
            return $this->configuration->getInstance();
        }

        if (null === $this->configuration->getClassName() || !is_string($this->configuration->getClassName())) {
            return $this->configuration->getInstance();
        }

        if (!class_exists($this->configuration->getClassName())) {
            throw new ClassNotFoundException(sprintf(
                'Class %s does not exist.', $this->presenter->presentString($this->configuration->getClassName())
            ), $this->configuration->getClassName());
        }

        $reflection = new ReflectionClass($this->configuration->getClassName());

        if (count($this->configuration->getArguments())) {
            try {
                $instance = $reflection->newInstanceArgs($this->configuration->getArguments());
            } catch (\ReflectionException $e) {
                if (strpos($e->getMessage(), 'does not have a constructor') !== 0) {
                    $className = $this->configuration->getClassName();
                    throw new MethodNotFoundException(sprintf(
                       'Method %s not found.',
                       $this->presenter->presentString($this->configuration->getClassName().'::__construct()')
                   ), new $className , '__construct', $this->configuration->getArguments());
                }
                throw $e;
            }
        } else {
            $instance = $reflection->newInstance();
        }

        $this->configuration->setInstance($instance);
        $this->configuration->setInstantiated(true);

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
}
