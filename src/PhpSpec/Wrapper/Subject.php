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

use PhpSpec\Wrapper\Subject\WrappedObject;
use PhpSpec\Wrapper\Subject\Caller;
use PhpSpec\Wrapper\Subject\SubjectWithArrayAccess;
use PhpSpec\Wrapper\Subject\Expectation;

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
    private $wrappedObject;
    private $caller;
    private $arrayAccess;

    public function __construct($subject, MatcherManager $matchers, Unwrapper $unwrapper,
                                PresenterInterface $presenter, EventDispatcherInterface $dispatcher,
                                ExampleNode $example, WrappedObject $wrappedObject = null,
                                Caller $caller = null, SubjectWithArrayAccess $arrayAccess = null)
    {
        $this->subject        = $subject;
        $this->matchers       = $matchers;
        $this->unwrapper      = $unwrapper;
        $this->presenter      = $presenter;
        $this->dispatcher     = $dispatcher;
        $this->example        = $example;
        $this->wrappedObject  = $wrappedObject ?: new WrappedObject($subject, $presenter, $unwrapper);
        $this->caller         = $caller ?: new Caller($this->wrappedObject, $example, $dispatcher, $presenter, $matchers, $unwrapper);
        $this->arrayAccess    = $arrayAccess ?: new SubjectWithArrayAccess($this->caller, $unwrapper, $presenter, $matchers, $dispatcher);
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
        return $this->createExpectation()->should($name, $arguments);
    }

    public function shouldNot($name = null, array $arguments = array())
    {
        return $this->createExpectation()->shouldNot($name, $arguments);
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
        return new static($value, $this->matchers, $this->unwrapper, $this->presenter, $this->dispatcher, $this->example);
    }

    public function createExpectation()
    {
        if ($this->subject === null) {
            $this->subject = $this->unwrapper->unwrapOne($this);
        }

        return new Expectation(
            $this->subject, $this->example, $this->dispatcher, $this->matchers
        );
    }
}
