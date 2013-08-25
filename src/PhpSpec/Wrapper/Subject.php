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
    private $matchers;
    private $unwrapper;
    private $presenter;
    private $dispatcher;
    private $example;
    private $subject;
    private $configuration;
    private $caller;

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
        $this->configuration  = new Subject\Configuration($subject, $presenter, $unwrapper);
        $this->caller         = new Subject\Caller($matchers, $unwrapper, $presenter, $dispatcher, $example, $this->configuration);
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
        if ($this->subject === null) {
            $this->subject = $this->unwrapper->unwrapOne($this);
        }
        $expectation = new Subject\Expectation(
            $this->subject, $this->matchers, $this->example, $this->unwrapper,
            $this->dispatcher
        );
        return $expectation->should($name, $arguments);
    }

    public function shouldNot($name = null, array $arguments = array())
    {
        if ($this->subject === null) {
            $this->subject = $this->unwrapper->unwrapOne($this);
        }
        $expectation = new Subject\Expectation(
            $this->subject, $this->matchers, $this->example, $this->unwrapper,
            $this->dispatcher
        );
        return $expectation->shouldNot($name, $arguments);
    }

    public function callOnWrappedObject($method, array $arguments = array())
    {
        return $this->caller->callOnWrappedObject($this, $method, $arguments);
    }

    public function setToWrappedObject($property, $value = null)
    {
        return $this->caller->setToWrappedObject($property, $value);
    }

    public function getFromWrappedObject($property)
    {
        return $this->caller->getFromWrappedObject($property);
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
}
