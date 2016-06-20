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

namespace PhpSpec\ServiceContainer;

use InvalidArgumentException;
use PhpSpec\ServiceContainer;

/**
 * The Service Container is a lightweight container based on Pimple to handle
 * object creation of PhpSpec services.
 */
final class IndexedServiceContainer implements ServiceContainer
{
    /**
     * @var array
     */
    private $parameters = [];

    /**
     * @var array
     */
    private $services = [];

    /**
     * @var array
     */
    private $tags = [];

    /**
     * @var array
     */
    private $configurators = [];

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
     * Sets a object to be used as a service
     *
     * @param string $id
     * @param object $value
     * @param array  $tags
     *
     * @throws \InvalidArgumentException if service is not an object or callable
     */
    public function set($id, $value, $tags=[])
    {
        if (!is_object($value)) {
            throw new InvalidArgumentException(sprintf(
                'Service should be an object, but %s given.',
                gettype($value)
            ));
        }

        $this->addToIndex($id, $value, $tags);
    }

    /**
     * Sets a callable for the service creation. The same service will
     * be returned every time
     *
     * @param string   $id
     * @param callable $callable
     * @param array    $tags
     *
     * @throws \InvalidArgumentException if service is not a callable
     */
    public function define($id, $callable, $tags = [])
    {
        if (!is_callable($callable)) {
            throw new InvalidArgumentException(sprintf(
                'Service should be callable, "%s" given.',
                gettype($callable)
            ));
        }

        $this->addToIndex(
            $id,
            function ($container) use ($callable) {
                static $instance;

                if (null === $instance) {
                    $instance = call_user_func($callable, $container);
                }

                return $instance;
            },
            $tags
        );
    }

    /**
     * Retrieves a service from the container
     *
     * @param string $id
     *
     * @return object
     *
     * @throws \InvalidArgumentException if service is not defined
     */
    public function get($id)
    {
        if (!array_key_exists($id, $this->services)) {
            throw new InvalidArgumentException(sprintf('Service "%s" is not defined.', $id));
        }

        $value = $this->services[$id];
        if (is_callable($value)) {
            return call_user_func($value, $this);
        }

        return $value;
    }

    /**
     * Determines whether a service is defined
     *
     * @param $id
     * @return bool
     */
    public function has($id)
    {
        return array_key_exists($id, $this->services);
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

        unset($this->services[$id]);
    }

    /**
     * Adds a service or service definition to the index
     *
     * @param string $id
     * @param mixed  $value
     * @param array  $tags
     */
    private function addToIndex($id, $value, array $tags)
    {
        foreach ($tags as $tag) {
            if (!isset($this->tags[$tag])) {
                $this->tags[$tag] = [];
            }
            $this->tags[$tag][] = $id;
        }

        $this->services[$id] = $value;
    }

    /**
     * Finds all services tagged with a particular string
     *
     * @param string $tag
     *
     * @return array
     */
    public function getByTag($tag)
    {
        return array_map([$this, 'get'], isset($this->tags[$tag]) ? $this->tags[$tag] : []);
    }

    /**
     * Adds a configurator, that can configure many services in one callable
     *
     * @internal
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
     *
     * @internal
     */
    public function configure()
    {
        foreach ($this->configurators as $configurator) {
            call_user_func($configurator, $this);
        }
    }
}
