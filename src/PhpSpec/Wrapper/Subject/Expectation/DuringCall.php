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

namespace PhpSpec\Wrapper\Subject\Expectation;

use PhpSpec\Exception\Example\MatcherException;
use PhpSpec\Matcher\Matcher;
use PhpSpec\Util\Instantiator;
use PhpSpec\Wrapper\Subject\WrappedObject;

abstract class DuringCall
{
    /**
     * @var Matcher
     */
    private $matcher;
    /**
     * @var mixed
     */
    private $subject;
    /**
     * @var array
     */
    private $arguments;
    /**
     * @var WrappedObject
     */
    private $wrappedObject;

    /**
     * @param Matcher $matcher
     */
    public function __construct(Matcher $matcher)
    {
        $this->matcher = $matcher;
    }

    /**
     * @param string $alias
     * @param mixed  $subject
     * @param array  $arguments
     *
     * @param WrappedObject|null $wrappedObject
     *
     * @return $this
     */
    public function match($alias, $subject, array $arguments = array(), $wrappedObject = null)
    {
        $this->subject = $subject;
        $this->arguments = $arguments;
        $this->wrappedObject = $wrappedObject;

        return $this;
    }

    /**
     * @param string $method
     * @param array  $arguments
     *
     * @return mixed
     */
    public function during($method, array $arguments = array())
    {
        if ($method === '__construct') {
            $this->subject->beAnInstanceOf($this->wrappedObject->getClassName(), $arguments);

            return $this->duringInstantiation();
        }

        $object = $this->wrappedObject->instantiate();

        return $this->runDuring($object, $method, $arguments);
    }

    /**
     * @return mixed
     */
    public function duringInstantiation()
    {
        if ($factoryMethod = $this->wrappedObject->getFactoryMethod()) {
            $method = is_array($factoryMethod) ? $factoryMethod[1] : $factoryMethod;
        } else {
            $method = '__construct';
        }
        $instantiator = new Instantiator();
        $object = $instantiator->instantiate($this->wrappedObject->getClassName());

        return $this->runDuring($object, $method, $this->wrappedObject->getArguments());
    }

    /**
     * @param string $method
     * @param array  $arguments
     *
     * @return mixed
     *
     * @throws MatcherException
     */
    public function __call($method, array $arguments = array())
    {
        if (preg_match('/^during(.+)$/', $method, $matches)) {
            return $this->during(lcfirst($matches[1]), $arguments);
        }

        throw new MatcherException('Incorrect usage of matcher Throw, '.
            'either prefix the method with "during" and capitalize the '.
            'first character of the method or use ->during(\'callable\', '.
            'array(arguments)).'.PHP_EOL.'E.g.'.PHP_EOL.'->during'.
            ucfirst($method).'(arguments)'.PHP_EOL.'or'.PHP_EOL.
            '->during(\''.$method.'\', array(arguments))');
    }

    /**
     * @return array
     */
    protected function getArguments()
    {
        return $this->arguments;
    }

    /**
     * @return Matcher
     */
    protected function getMatcher()
    {
        return $this->matcher;
    }

    /**
     * @param object $object
     * @param string $method
     * @param array  $arguments
     *
     * @return mixed
     */
    abstract protected function runDuring($object, $method, array $arguments = array());
}
