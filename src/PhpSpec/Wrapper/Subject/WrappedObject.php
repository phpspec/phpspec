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

use PhpSpec\Factory\ObjectFactory;
use PhpSpec\Formatter\Presenter\Presenter;
use PhpSpec\Wrapper\Subject\Expectation\Expectation;
use PhpSpec\Wrapper\Unwrapper;
use PhpSpec\Exception\Wrapper\SubjectException;

class WrappedObject
{
    /**
     * @var ?class-string
     */
    private ?string $classname = null;
    /**
     * @var null|callable|string
     */
    private mixed $factoryMethod = null;
    private array $arguments = array();
    private bool $isInstantiated = false;

    public function __construct(private mixed $instance, private Presenter $presenter)
    {
        if (\is_object($this->instance)) {
            $this->classname = \get_class($this->instance);
            $this->isInstantiated = true;
        }
    }

    /**
     * @param class-string $classname
     *
     * @throws SubjectException
     */
    public function beAnInstanceOf(string $classname, array $arguments = array()): void
    {
        $this->classname      = $classname;
        $unwrapper            = new Unwrapper();
        $this->arguments      = $unwrapper->unwrapAll($arguments);
        $this->isInstantiated = false;
        $this->factoryMethod  = null;
    }

    /**
     * @throws SubjectException
     */
    public function beConstructedWith(array $args): void
    {
        if (null === $this->classname) {
            throw new SubjectException(sprintf(
                'You can not set object arguments. Behavior subject is %s.',
                $this->presenter->presentValue(null)
            ));
        }

        if ($this->isInstantiated()) {
            throw new SubjectException('You can not change object construction method when it is already instantiated');
        }

        $this->beAnInstanceOf($this->classname, $args);
    }

    public function beConstructedThrough(null|callable|string $factoryMethod, array $arguments = array()): void
    {

        if (\is_string($factoryMethod) && !str_contains($factoryMethod, '::')) {
            if (!$this->classname) {
                throw new \LogicException('Cannot call factory method on non-obect');
            }
            if (method_exists($this->classname, $factoryMethod)) {
                $factoryMethod = array($this->classname, $factoryMethod);
            }
        }

        if ($this->isInstantiated()) {
            throw new SubjectException('You can not change object construction method when it is already instantiated');
        }

        $this->factoryMethod = $factoryMethod;
        $unwrapper           = new Unwrapper();
        $this->arguments     = $unwrapper->unwrapAll($arguments);
    }

    public function getFactoryMethod(): callable|null|string
    {
        return $this->factoryMethod;
    }

    
    public function isInstantiated(): bool
    {
        return $this->isInstantiated;
    }

    
    public function setInstantiated(bool $instantiated): void
    {
        $this->isInstantiated = $instantiated;
    }

    /** @return ?class-string */
    public function getClassName(): ?string
    {
        return $this->classname;
    }

    /** @param class-string $classname */
    public function setClassName(string $classname): void
    {
        $this->classname = $classname;
    }

    
    public function getArguments(): array
    {
        return $this->arguments;
    }

    public function getInstance(): mixed
    {
        return $this->instance;
    }

    public function setInstance(object $instance): void
    {
        $this->instance = $instance;
    }

    /**
     * @psalm-assert true $this->isInstantiated
     * @psalm-assert object $this->instance
     */
    public function instantiate(): object
    {
        if ($this->isInstantiated()) {
            return $this->instance;
        }

        if ($this->factoryMethod) {
            if (!is_callable($this->factoryMethod)) {
                throw new \LogicException('Factory method must be callable');
            }
            $this->instance = (new ObjectFactory())->instantiateFromCallable(
                $this->factoryMethod,
                $this->arguments
            );
        } else {
            if (is_null($this->classname)) {
                throw new \LogicException('Class name was not set');
            }
            $reflection = new \ReflectionClass($this->classname);

            $this->instance = empty($this->arguments) ?
                $reflection->newInstance() :
                $reflection->newInstanceArgs($this->arguments);
        }

        $this->isInstantiated = true;

        return $this->instance;
    }
}
