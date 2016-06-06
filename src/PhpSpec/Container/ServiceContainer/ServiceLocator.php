<?php

namespace PhpSpec\Container\ServiceContainer;

interface ServiceLocator
{
    /**
     * @param string $serviceId
     * @return mixed
     */
    public function get($serviceId);

    /**
     * @param string $serviceIdPrefix
     * @return mixed
     */
    public function getByPrefix($serviceIdPrefix);
}
