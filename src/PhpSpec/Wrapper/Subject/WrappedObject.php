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
use PhpSpec\Wrapper\Unwrapper;
use PhpSpec\Exception\Wrapper\SubjectException;

class WrappedObject
{
    /**
     * @var object
     */
    private $instance;
    /**
     * @var Presenter
     */
    private $presenter;
    /**
     * @var string
     */
    private $classname;
    /**
     * @var callable|null
     */
    private $factoryMethod;
    /**
     * @var array
     */
    private $arguments = array();
    /**
     * @var bool
     */
    private $isInstantiated = false;

    /**
     * @param object|null        $instance
     * @param Presenter $presenter
     */
    public function __construct($instance, Presenter $presenter)
    {
        $this->instance = $instance;
        $this->presenter = $presenter;
        if (\is_object($this->instance)) {
            $this->classname = \get_class($this->instance);
            $this->isInstantiated = true;
        }
    }

    /**
     * @param string $classname
     * @param array  $arguments
     *
     * @throws \PhpSpec\Exception\Wrapper\SubjectException
     */
    public function beAnInstanceOf(string $classname, array $arguments = array()): void
    {
        if (!\is_string($classname)) {
            throw new SubjectException(sprintf(
                'Behavior subject classname should be a string, %s given.',
                $this->presenter->presentValue($classname)
            ));
        }

        $this->classname      = $classname;
        $unwrapper            = new Unwrapper();
        $this->arguments      = $unwrapper->unwrapAll($arguments);
        $this->isInstantiated = false;
        $this->factoryMethod  = null;
    }

    /**
     * @param array $args
     *
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

    /**
     * @param callable|string|null $factoryMethod
     * @param array                $arguments
     */
    public function beConstructedThrough($factoryMethod, array $arguments = array()): void
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

    /**
     * @return callable|null
     */
    public function getFactoryMethod()
    {
        return $this->factoryMethod;
    }

    /**
     * @return bool
     */
    public function isInstantiated(): bool
    {
        return $this->isInstantiated;
    }

    /**
     * @param boolean $instantiated
     */
    public function setInstantiated(bool $instantiated): void
    {
        $this->isInstantiated = $instantiated;
    }

    /**
     * @return string|null
     */
    public function getClassName()
    {
        return $this->classname;
    }

    /**
     * @param string $classname
     */
    public function setClassName(string $classname): void
    {
        $this->classname = $classname;
    }

    /**
     * @return array
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    /**
     * @return object|null
     */
    public function getInstance()
    {
        return $this->instance;
    }

    /**
     * @param object $instance
     */
    public function setInstance($instance): void
    {
        $this->instance = $instance;
    }

    /**
     * @return object
     */
    public function instantiate()
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
