<?php

namespace PhpSpec\Container\ServiceContainer;

interface ConfigObject
{
    /**
     * @param string $key
     * @param mixed  $defaultReturnValue
     * @return mixed
     */
    public function getParam($key, $defaultReturnValue = null);

    /**
     * @param string $key
     * @param mixed  $value
     */
    public function setParam($key, $value);
}
