<?php

/*
 * This file is part of PhpSpec, A php toolset to drive emergent
 * design by specification.
 *
 * (c) Marcello Duarte <marcello.duarte@gmail.com>
 * (c) Konstantin Kudryashov <ever.zet@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpSpec\Wrapper\Subject;

use PhpSpec\Exception\Fracture\MethodNotFoundException;
use PhpSpec\Exception\Fracture\MethodNotVisibleException;
use PhpSpec\Exception\Wrapper\SubjectException;
use PhpSpec\Exception\Fracture\PropertyNotFoundException;
use PhpSpec\Exception\Fracture\ClassNotFoundException;
use PhpSpec\Exception\Fracture\FactoryDoesNotReturnObjectException;
use PhpSpec\CodeAnalysis\AccessInspector;
use PhpSpec\Exception\ExceptionFactory;
use PhpSpec\Exception\Fracture\NamedConstructorNotFoundException;
use PhpSpec\Factory\ObjectFactory;
use PhpSpec\Loader\Node\ExampleNode;
use PhpSpec\Wrapper\Subject;
use PhpSpec\Wrapper\Wrapper;
use PhpSpec\Wrapper\Unwrapper;
use PhpSpec\Event\MethodCallEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface as Dispatcher;
use ReflectionClass;
use ReflectionException;

class Caller
{
    private WrappedObject $wrappedObject;
    private ExampleNode $example;
    private Dispatcher $dispatcher;
    private Wrapper $wrapper;
    private ExceptionFactory $exceptionFactory;
    private AccessInspector $accessInspector;

    
    public function __construct(
        WrappedObject $wrappedObject,
        ExampleNode $example,
        Dispatcher $dispatcher,
        ExceptionFactory $exceptions,
        Wrapper $wrapper,
        AccessInspector $accessInspector
    ) {
        $this->wrappedObject    = $wrappedObject;
        $this->example          = $example;
        $this->dispatcher       = $dispatcher;
        $this->wrapper          = $wrapper;
        $this->exceptionFactory = $exceptions;
        $this->accessInspector  = $accessInspector;
    }

    /**
     * @throws MethodNotFoundException
     * @throws MethodNotVisibleException
     * @throws SubjectException
     */
    public function call(string $method, array $arguments = array()): Subject
    {
        if (!is_object($this->getWrappedObject())) {
            throw $this->callingMethodOnNonObject($method);
        }

        $subject   = $this->wrappedObject->getInstance();
        $unwrapper = new Unwrapper();
        $arguments = $unwrapper->unwrapAll($arguments);

        if ($this->isObjectMethodCallable($method)) {
            return $this->invokeAndWrapMethodResult($subject, $method, $arguments);
        }

        throw $this->methodNotFound($method, $arguments);
    }

    /**
     * @throws SubjectException
     * @throws PropertyNotFoundException
     */
    public function set(string $property, mixed $value = null): void
    {
        if (null === $this->getWrappedObject()) {
            throw $this->settingPropertyOnNonObject($property);
        }

        $unwrapper = new Unwrapper();
        $value = $unwrapper->unwrapOne($value);

        if ($this->isObjectPropertyWritable($property)) {
            $this->getWrappedObject()->$property = $value;

            return;
        }

        throw $this->propertyNotFound($property);
    }

    /**
     * @throws PropertyNotFoundException
     * @throws SubjectException
     */
    public function get(string $property) : mixed
    {
        if ($this->lookingForConstants($property) && $this->constantDefined($property)) {
            return constant($this->wrappedObject->getClassName().'::'.$property);
        }

        if (null === $this->getWrappedObject()) {
            throw $this->accessingPropertyOnNonObject($property);
        }

        if ($this->isObjectPropertyReadable($property)) {
            return $this->wrap($this->getWrappedObject()->$property);
        }

        throw $this->propertyNotFound($property);
    }

    /**
     * @throws ClassNotFoundException
     */
    public function getWrappedObject(): mixed
    {
        if ($this->wrappedObject->isInstantiated()) {
            return $this->wrappedObject->getInstance();
        }

        $className = $this->wrappedObject->getClassName();
        if (!\is_string($className)) {
            return $this->wrappedObject->getInstance();
        }

        if (!$className || !class_exists($className)) {
            throw $this->classNotFound();
        }

        if (\is_object($this->wrappedObject->getInstance())) {
            $this->wrappedObject->setInstantiated(true);
            $instance = $this->wrappedObject->getInstance();
        } else {
            $instance = $this->instantiateWrappedObject();
            $this->wrappedObject->setInstance($instance);
            $this->wrappedObject->setInstantiated(true);
        }

        return $instance;
    }

    
    private function isObjectPropertyReadable(string $property): bool
    {
        $subject = $this->getWrappedObject();

        return \is_object($subject) && $this->accessInspector->isPropertyReadable($subject, $property);
    }

    
    private function isObjectPropertyWritable(string $property): bool
    {
        $subject = $this->getWrappedObject();

        return \is_object($subject) && $this->accessInspector->isPropertyWritable($subject, $property);
    }

    
    private function isObjectMethodCallable(string $method): bool
    {
        return $this->accessInspector->isMethodCallable($this->getWrappedObject(), $method);
    }

    private function instantiateWrappedObject(): object
    {
        if ($this->wrappedObject->getFactoryMethod()) {
            return $this->newInstanceWithFactoryMethod();
        }

        /** @var class-string $className already validated before this method is called*/
        $className = $this->wrappedObject->getClassName();
        $reflection = new ReflectionClass($className);

        if (\count($this->wrappedObject->getArguments())) {
            return $this->newInstanceWithArguments($reflection);
        }

        return $reflection->newInstance();
    }

    private function invokeAndWrapMethodResult(object $subject, string $method, array $arguments = array()): Subject
    {
        $this->dispatcher->dispatch(
            new MethodCallEvent($this->example, $subject, $method, $arguments),
            'beforeMethodCall'
        );

        $returnValue = \call_user_func_array(array($subject, $method), $arguments);

        $this->dispatcher->dispatch(
            new MethodCallEvent($this->example, $subject, $method, $arguments),
            'afterMethodCall'
        );

        return $this->wrap($returnValue);
    }

    
    private function wrap(mixed $value): Subject
    {
        return $this->wrapper->wrap($value);
    }

    /**
     * @throws MethodNotFoundException
     * @throws MethodNotVisibleException
     * @throws \Exception
     * @throws \ReflectionException
     */
    private function newInstanceWithArguments(ReflectionClass $reflection): object
    {
        try {
            return $reflection->newInstanceArgs($this->wrappedObject->getArguments());
        } catch (ReflectionException $e) {
            if ($this->detectMissingConstructorMessage($e)) {
                throw $this->methodNotFound(
                    '__construct',
                    $this->wrappedObject->getArguments()
                );
            }
            throw $e;
        }
    }

    /**
     * @throws MethodNotFoundException
     * @throws FactoryDoesNotReturnObjectException
     */
    private function newInstanceWithFactoryMethod(): object
    {
        /** @var callable $method Already checked in caller  */
        $method = $this->wrappedObject->getFactoryMethod();

        /** @var class-string $className Already checked in caller */
        $className = $this->wrappedObject->getClassName();

        if (\is_string($method) && !method_exists($className, $method)) {
            throw $this->namedConstructorNotFound(
                $method,
                $this->wrappedObject->getArguments()
            );
        }

        return (new ObjectFactory())->instantiateFromCallable(
            $method,
            $this->wrappedObject->getArguments()
        );
    }

    
    private function detectMissingConstructorMessage(ReflectionException $exception): bool
    {
        return strpos(
            $exception->getMessage(),
            'does not have a constructor'
        ) !== 0;
    }


    private function classNotFound(): ClassNotFoundException
    {
        return $this->exceptionFactory->classNotFound($this->wrappedObject->getClassName() ?? '');
    }

    private function namedConstructorNotFound(string $method, array $arguments = array()) : NamedConstructorNotFoundException
    {
        $className = $this->wrappedObject->getClassName();

        return $this->exceptionFactory->namedConstructorNotFound($className ?? '', $method, $arguments);
    }

    private function methodNotFound(string $method, array $arguments = array()): MethodNotFoundException|MethodNotVisibleException
    {
        /** @var class-string $className */
        $className = $this->wrappedObject->getClassName();

        if (!method_exists($className, $method)) {
            return $this->exceptionFactory->methodNotFound($className, $method, $arguments);
        }

        return $this->exceptionFactory->methodNotVisible($className, $method, $arguments);
    }

    
    private function propertyNotFound(string $property): PropertyNotFoundException
    {
        return $this->exceptionFactory->propertyNotFound($this->getWrappedObject(), $property);
    }

    
    private function callingMethodOnNonObject(string $method): SubjectException
    {
        return $this->exceptionFactory->callingMethodOnNonObject($method);
    }

    
    private function settingPropertyOnNonObject(string $property): SubjectException
    {
        return $this->exceptionFactory->settingPropertyOnNonObject($property);
    }

    
    private function accessingPropertyOnNonObject(string $property): SubjectException
    {
        return $this->exceptionFactory->gettingPropertyOnNonObject($property);
    }

    
    private function lookingForConstants(string $property): bool
    {
        return null !== $this->wrappedObject->getClassName() &&
            $property === strtoupper($property);
    }

    
    public function constantDefined(string $property): bool
    {
        return \defined($this->wrappedObject->getClassName().'::'.$property);
    }
}
