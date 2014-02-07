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

namespace PhpSpec;

use InvalidArgumentException;

/**
 * The Service Container is a lightweight container based on Pimple to handle
 * object creation of PhpSpec services.
 */
class ServiceContainer
{
    /**
     * @var array
     */
    private $parameters = array();

    /**
     * @var array
     */
    private $services = array();

    /**
     * @var array
     */
    private $prefixed = array();

    /**
     * @var array
     */
    private $configurators = array();

    /**
     * Sets a param in the container
     *
     * @param string $id
     * @param mixed  $value
     */
    public function setParam($id, $value)
    {
        $this->parameters[$id] = $value;
    }

    /**
     * Gets a param from the container or a default value.
     *
     * @param string $id
     * @param mixed  $default
     *
     * @return mixed
     */
    public function getParam($id, $default = null)
    {
        return isset($this->parameters[$id]) ? $this->parameters[$id] : $default;
    }

    /**
     * Sets a object or a callback for the object creation. A new object will
     * be created every time
     *
     * @param string          $id
     * @param object|callable $value
     *
     * @throws \InvalidArgumentException if service is not an object or callback
     */
    public function set($id, $value)
    {
        if (!is_object($value)) {
            throw new InvalidArgumentException(sprintf(
                'Service should be callback or object, but %s given.',gettype($value)
            ));
        }

        list($prefix, $sid) = $this->getPrefixAndSid($id);
        if ($prefix) {
            if (!isset($this->prefixed[$prefix])) {
                $this->prefixed[$prefix] = array();
            }

            $this->prefixed[$prefix][$sid] = $id;
        }

        $this->services[$id] = $value;
    }

    /**
     * Sets a object or a callback for the object creation. The same object will
     * be returned every time
     *
     * @param string   $id
     * @param callable $callable
     *
     * @throws \InvalidArgumentException if service is not an object or callback
     */
    public function setShared($id, $callable)
    {
        if (!is_object($callable)) {
            throw new InvalidArgumentException(sprintf(
                'Service should be callback, "%s" given.', gettype($callable)
            ));
        }

        $this->set($id, function ($container) use ($callable) {
            static $instance;

            if (null === $instance) {
                $instance = $callable($container);
            }

            return $instance;
        });
    }

    /**
     * Retrieves a service from the container
     *
     * @param string $id
     *
     * @return mixed
     *
     * @throws \InvalidArgumentException if service is not defined
     */
    public function get($id)
    {
        if (!array_key_exists($id, $this->services)) {
            throw new InvalidArgumentException(sprintf('Service "%s" is not defined.', $id));
        }

        $value = $this->services[$id];
        if (method_exists($value, '__invoke')) {
            return $value($this);
        }

        return $value;
    }

    /**
     * Retrieves a list of services of a given prefix
     *
     * @param string $prefix
     *
     * @return array
     */
    public function getByPrefix($prefix)
    {
        if (!array_key_exists($prefix, $this->prefixed)) {
            return array();
        }

        $services = array();
        foreach ($this->prefixed[$prefix] as $id) {
            $services[] = $this->get($id);
        }

        return $services;
    }

    /**
     * Removes a service from the container
     *
     * @param string $id
     *
     * @throws \InvalidArgumentException if service is not defined
     */
    public function remove($id)
    {
        if (!array_key_exists($id, $this->services)) {
            throw new InvalidArgumentException(sprintf('Service "%s" is not defined.', $id));
        }

        list($prefix, $sid) = $this->getPrefixAndSid($id);
        if ($prefix) {
            unset($this->prefixed[$prefix][$sid]);
        }

        unset($this->services[$id]);
    }

    /**
     * Adds a configurator, that can configure many services in one callback
     *
     * @param callable $configurator
     *
     * @throws \InvalidArgumentException if configurator is not a callback
     */
    public function addConfigurator($configurator)
    {
        if (!is_object($configurator)) {
            throw new InvalidArgumentException(sprintf(
                'Configurator should be callback or object, but %s given.', gettype($configurator)
            ));
        }

        $this->configurators[] = $configurator;
    }

    /**
     * Loop through all configurators and invoke them
     */
    public function configure()
    {
        foreach ($this->configurators as $configurator) {
            $configurator($this);
        }
    }

    /**
     * Retrieves the prefix and sid of a given service
     *
     * @param string $id
     *
     * @return array
     */
    private function getPrefixAndSid($id)
    {
        if (count($parts = explode('.', $id)) < 2) {
            return array(null, $id);
        }

        $sid    = array_pop($parts);
        $prefix = implode('.', $parts);

        return array($prefix, $sid);
    }
}
