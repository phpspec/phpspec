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

use Closure;
use PhpSpec\Factory\ObjectFactory;
use PhpSpec\Formatter\Presenter\Presenter;
use PhpSpec\Wrapper\Unwrapper;
use PhpSpec\Exception\Wrapper\SubjectException;

class WrappedObject
{
    private ?string $classname = null;

    /**
     * @var null|callable
     */
    private mixed $factoryMethod;

    private array $arguments =[];

    private bool $isInstantiated = false;

    public function __construct(
        private mixed $instance,
        private Presenter $presenter
    )
    {
        if (\is_object($this->instance)) {
            $this->classname = \get_class($this->instance);
            $this->isInstantiated = true;
        }
    }

    /**
     * @throws \PhpSpec\Exception\Wrapper\SubjectException
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
     * @throws \PhpSpec\Exception\Wrapper\SubjectException
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

    public function beConstructedThrough(callable|array|string|null $factoryMethod, array $arguments = array()): void
    {
        if (\is_string($factoryMethod) &&
            false === strpos($factoryMethod, '::') &&
            method_exists($this->classname, $factoryMethod)
        ) {
            $factoryMethod = array($this->classname, $factoryMethod);
        }

        if ($this->isInstantiated()) {
            throw new SubjectException('You can not change object construction method when it is already instantiated');
        }

        $this->factoryMethod = $factoryMethod;
        $unwrapper           = new Unwrapper();
        $this->arguments     = $unwrapper->unwrapAll($arguments);
    }

    public function getFactoryMethod() : callable|array|string|null
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

    public function getClassName() : ?string
    {
        return $this->classname;
    }

    public function setClassName(string $classname): void
    {
        $this->classname = $classname;
    }

    public function getArguments(): array
    {
        return $this->arguments;
    }

    public function getInstance() : mixed
    {
        return $this->instance;
    }

    public function setInstance(object $instance): void
    {
        $this->instance = $instance;
    }

    public function instantiate() : object
    {
        if ($this->isInstantiated()) {
            return $this->instance;
        }

        if ($this->factoryMethod) {
            $this->instance = (new ObjectFactory())->instantiateFromCallable(
                $this->factoryMethod,
                $this->arguments
            );
        } else {
            $reflection = new \ReflectionClass($this->classname);

            $this->instance = empty($this->arguments) ?
                $reflection->newInstance() :
                $reflection->newInstanceArgs($this->arguments);
        }

        $this->isInstantiated = true;

        return $this->instance;
    }
}
