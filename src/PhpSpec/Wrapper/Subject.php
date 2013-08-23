<?php

namespace PhpSpec\Wrapper;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use PhpSpec\Event\MethodCallEvent;
use PhpSpec\Formatter\Presenter\PresenterInterface;
use PhpSpec\Loader\Node\ExampleNode;
use PhpSpec\Runner\MatcherManager;

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
    private $configuration;
    private $matchers;
    private $unwrapper;
    private $presenter;
    private $dispatcher;
    private $example;
    private $subject;

    public function __construct($subject, MatcherManager $matchers, Unwrapper $unwrapper,
                                PresenterInterface $presenter, EventDispatcherInterface $dispatcher,
                                ExampleNode $example)
    {
        $this->subject        = $subject;
        $this->matchers       = $matchers;
        $this->unwrapper      = $unwrapper;
        $this->presenter      = $presenter;
        $this->dispatcher     = $dispatcher;
        $this->example        = $example;
        $this->configuration  = new Subject\Configuration($this, $presenter, $unwrapper);
    }

    public function beAnInstanceOf($className, array $arguments = array())
    {
        $this->configuration->beAnInstanceOf($className, $arguments);
    }

    public function beConstructedWith()
    {
        $this->configuration->beConstructedWith(func_get_args());
    }

    public function should($name = null, array $arguments = array())
    {
        return (new Subject\Expectation($this, $this->matchers, $this->example, $this->unwrapper, $this->dispatcher))
            ->should($name, $arguments);
    }

    public function shouldNot($name = null, array $arguments = array())
    {
        return (new Subject\Expectation($this, $this->matchers, $this->example, $this->unwrapper, $this->dispatcher))
            ->shouldNot($name, $arguments);
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

            $this->dispatcher->dispatch('beforeMethodCall',
                new MethodCallEvent($this->example, $subject, $method, $arguments)
            );

            $returnValue = call_user_func_array(array($subject, $method), $arguments);

            $this->dispatcher->dispatch('afterMethodCall',
                new MethodCallEvent($this->example, $subject, $method, $arguments)
            );

            return new static($returnValue, $this->matchers, $this->unwrapper, $this->presenter, $this->dispatcher, $this->example);
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
            if (defined($this->configuration->getClassName() . '::'.$property)) {
                return constant($this->configuration->getClassName() . '::' . $property);
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

            return new static($returnValue, $this->matchers, $this->unwrapper, $this->presenter, $this->dispatcher, $this->example);
        }

        throw new PropertyNotFoundException(sprintf(
            'Property %s not found.',
            $this->presenter->presentString(get_class($this->getWrappedObject()).'::'.$property)
        ), $this->getWrappedObject(), $property);
    }

    public function getWrappedObject()
    {
        if ($this->configuration->isInstantiated()) {
            return $this->subject;
        }

        if (null === $this->configuration->getClassName() || !is_string($this->configuration->getClassName())) {
            throw new Exception(sprintf(
                'Instantiator expects class name, got %s.',
                $this->presenter->presentValue($this->configuration->getClassName())
            ));
        }

        if (!class_exists($this->configuration->getClassName())) {
            throw new ClassNotFoundException(sprintf(
                'Class %s does not exist.', $this->presenter->presentString($this->configuration->getClassName())
            ), $this->configuration->getClassName());
        }

        $this->configuration->setInstantiated(true);

        $reflection = new ReflectionClass($this->configuration->getClassName());

        if (count($this->configuration->getArguments())) {
            try {
                return $this->subject = $reflection->newInstanceArgs($this->configuration->getArguments());
            } catch (\ReflectionException $e) {
                if (strpos($e->getMessage(), 'does not have a constructor') !== 0) {
                    $className = $this->configuration->getClassName();
                    throw new MethodNotFoundException(sprintf(
                       'Method %s not found.',
                       $this->presenter->presentString($this->configuration->getClassName().'::__construct()')
                   ), new $className, '__construct', $this->configuration->getArguments());
                }
                throw $e;
            }
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

        return new static($subject[$key], $this->matchers, $this->unwrapper, $this->presenter, $this->dispatcher, $this->example);
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
        // if user calls function with should prefix - call m2atcher
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
