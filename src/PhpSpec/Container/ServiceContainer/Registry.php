<?php

namespace PhpSpec\Container\ServiceContainer;

interface Registry
{
    /**
     * @param string $serviceId
     * @param object $object
     */
    public function set($serviceId, $object);

    /**
     * @param string   $serviceId
     * @param callable $factoryClosure
     */
    public function setShared($serviceId, callable $factoryClosure);
}
