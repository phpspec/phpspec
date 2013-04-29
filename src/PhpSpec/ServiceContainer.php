<?php

namespace PhpSpec;

use InvalidArgumentException;
use RuntimeException;

class ServiceContainer
{
    private $parameters    = array();
    private $services      = array();
    private $prefixed      = array();
    private $configurators = array();

    public function setParam($id, $value)
    {
        $this->parameters[$id] = $value;
    }

    public function getParam($id, $default = null)
    {
        return isset($this->parameters[$id]) ? $this->parameters[$id] : $default;
    }

    public function set($id, $value)
    {
        if (!is_object($value)) {
            throw new InvalidArgumentException(sprintf(
                'Service should be callback or object, but %s given.', gettype($value)
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

    public function setShared($id, $callable)
    {
        if (!is_object($callable)) {
            throw new InvalidArgumentException(sprintf(
                'Service should be callback, "%s" given.', gettype($callable)
            ));
        }

        $this->set($id, function($container) use ($callable) {
            static $instance;

            if (null === $instance) {
                $instance = $callable($container);
            }

            return $instance;
        });
    }

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

    public function addConfigurator($configurator)
    {
        if (!is_object($configurator)) {
            throw new InvalidArgumentException(sprintf(
                'Configurator should be callback or object, but %s given.', gettype($configurator)
            ));
        }

        $this->configurators[] = $configurator;
    }

    public function configure()
    {
        foreach ($this->configurators as $configurator) {
            $configurator($this);
        }
    }

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
