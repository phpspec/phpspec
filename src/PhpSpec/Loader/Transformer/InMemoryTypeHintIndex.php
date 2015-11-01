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

namespace PhpSpec\Loader\Transformer;

final class InMemoryTypeHintIndex implements TypeHintIndex
{
    /**
     * @var array
     */
    private $typehints = array();

    /**
     * @param string $class
     * @param string $method
     * @param string $argument
     * @param string $typehint
     */
    public function add($class, $method, $argument, $typehint)
    {
        $this->store($class, $method, $argument, $typehint);
    }

    /**
     * @param string $class
     * @param string $method
     * @param string $argument
     * @param \Exception $exception
     */
    public function addInvalid($class, $method, $argument, \Exception $exception)
    {
        $this->store($class, $method, $argument, $exception);
    }

    /**
     * @param string $class
     * @param string $method
     * @param string $argument
     * @param mixed $typehint
     */
    private function store($class, $method, $argument, $typehint)
    {
        $class = strtolower($class);
        $method = strtolower($method);
        $argument = strtolower($argument);

        if (!array_key_exists($class, $this->typehints)) {
            $this->typehints[$class] = array();
        }
        if (!array_key_exists($method, $this->typehints[$class])) {
            $this->typehints[$class][$method] = array();
        }

        $this->typehints[$class][$method][$argument] = $typehint;
    }

    /**
     * @param string $class
     * @param string $method
     * @param string $argument
     *
     * @return string|null
     */
    public function lookup($class, $method, $argument)
    {
        $class = strtolower($class);
        $method = strtolower($method);
        $argument = strtolower($argument);

        if (!isset($this->typehints[$class][$method][$argument])) {
            return false;
        }

        if ($this->typehints[$class][$method][$argument] instanceof \Exception) {
            throw $this->typehints[$class][$method][$argument];
        };

        return $this->typehints[$class][$method][$argument];
    }
}
