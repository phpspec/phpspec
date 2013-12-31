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

use PhpSpec\Formatter\Presenter\PresenterInterface;
use PhpSpec\Wrapper\Unwrapper;
use PhpSpec\Exception\Wrapper\SubjectException;

/**
 * Class WrappedObject
 * @package PhpSpec\Wrapper\Subject
 */
class WrappedObject
{
    /**
     * @var object
     */
    private $instance;
    /**
     * @var \PhpSpec\Formatter\Presenter\PresenterInterface
     */
    private $presenter;
    /**
     * @var string
     */
    private $classname;
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
     * @param PresenterInterface $presenter
     */
    public function __construct($instance, PresenterInterface $presenter)
    {
        $this->instance = $instance;
        $this->presenter = $presenter;
        if (is_object($this->instance)) {
            $this->classname = get_class($this->instance);
            $this->isInstantiated = true;
        }
    }

    /**
     * @param string $classname
     * @param array  $arguments
     *
     * @throws \PhpSpec\Exception\Wrapper\SubjectException
     */
    public function beAnInstanceOf($classname, array $arguments = array())
    {
        if (!is_string($classname)) {
            throw new SubjectException(sprintf(
                'Behavior subject classname should be a string, %s given.',
                $this->presenter->presentValue($classname)
            ));
        }

        $this->classname      = $classname;
        $unwrapper            = new Unwrapper;
        $this->arguments      = $unwrapper->unwrapAll($arguments);
        $this->isInstantiated = false;
    }

    /**
     * @param array $args
     *
     * @throws \PhpSpec\Exception\Wrapper\SubjectException
     */
    public function beConstructedWith($args)
    {
        if (null === $this->classname) {
            throw new SubjectException(sprintf(
                'You can not set object arguments. Behavior subject is %s.',
                $this->presenter->presentValue(null)
            ));
        }

        $this->beAnInstanceOf($this->classname, $args);
    }

    /**
     * @return bool
     */
    public function isInstantiated()
    {
        return $this->isInstantiated;
    }

    /**
     * @param boolean $instantiated
     */
    public function setInstantiated($instantiated)
    {
        $this->isInstantiated = $instantiated;
    }

    /**
     * @return string
     */
    public function getClassName()
    {
        return $this->classname;
    }

    /**
     * @param string $classname
     */
    public function setClassName($classname)
    {
        $this->classname = $classname;
    }

    /**
     * @return array
     */
    public function getArguments()
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
    public function setInstance($instance)
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

        $reflection = new \ReflectionClass($this->classname);

        if (empty($this->arguments)) {
            $this->instance = $reflection->newInstance();
        } else {
            $this->instance = $reflection->newInstanceArgs($this->arguments);
        }

        $this->isInstantiated = true;

        return $this->instance;
    }
}
