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

namespace PhpSpec\Container;

use Interop\Container\ContainerInterface;
use InvalidArgumentException;

/**
 * The Service Container is a lightweight container based on Pimple to handle
 * object creation of PhpSpec services.
 */
class ServiceContainer implements ContainerInterface
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
     * Sets a object or a callable for the object creation. A callable will be invoked
     * every time get is called.
     *
     * @param string          $serviceId
     * @param object|callable $value
     *
     * @throws \InvalidArgumentException if service is not an object or callable
     */
    public function set($serviceId, $value)
    {
        if (!is_object($value) && !is_callable($value)) {
            ServiceNotFound::constructFromServiceId($serviceId);
        }

        list($prefix, $sid) = $this->getPrefixAndSid($serviceId);
        if ($prefix) {
            if (!isset($this->prefixed[$prefix])) {
                $this->prefixed[$prefix] = array();
            }

            $this->prefixed[$prefix][$sid] = $serviceId;
        }

        $this->services[$serviceId] = $value;
    }

    /**
     * Sets a callable for the object creation. The same object will
     * be returned every time
     *
     * @param string   $id
     * @param callable $callable
     *
     * @throws \InvalidArgumentException if service is not a callable
     */
    public function setShared($id, $callable)
    {
        if (!is_callable($callable)) {
            throw new ContainerException(sprintf(
                'Service should be callable, "%s" given.',
                gettype($callable)
            ));
        }

        $this->set($id, function ($container) use ($callable) {
            static $instance;

            if (null === $instance) {
                $instance = call_user_func($callable, $container);
            }

            return $instance;
        });
    }

    /**
     * Retrieves a service from the container
     *
     * @param string $serviceId
     *
     * @return object
     *
     * @throws \InvalidArgumentException if service is not defined
     */
    public function get($serviceId)
    {
        if (!$this->has($serviceId)) {
            throw ServiceNotFound::constructFromServiceId($serviceId);
        }

        $value = $this->services[$serviceId];
        if (is_callable($value)) {
            return call_user_func($value, $this);
        }

        return $value;
    }

    /**
     * @param string $serviceId
     * @return bool
     */
    public function has($serviceId)
    {
        return array_key_exists($serviceId, $this->services);
    }

    /**
     * @deprecated Use has()
     * @param $serviceId
     * @return bool
     */
    public function isDefined($serviceId)
    {
        return $this->has($serviceId);
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
     * Adds a configurator, that can configure many services in one callable
     *
     * @param callable $configurator
     *
     * @throws \InvalidArgumentException if configurator is not a callable
     */
    public function addConfigurator($configurator)
    {
        if (!is_callable($configurator)) {
            throw new InvalidArgumentException(sprintf(
                'Configurator should be callable, but %s given.',
                gettype($configurator)
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
            call_user_func($configurator, $this);
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
