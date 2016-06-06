<?php

namespace PhpSpec\Container;

use Interop\Container\ContainerInterface;
use PhpSpec\Console\ContainerAssembler;

class ContainerBuilder
{
    /**
     * @return ContainerInterface
     */
    public function buildContainer()
    {
        $container = new ServiceContainer();
        $containerConfig = new ContainerAssembler();
        $containerConfig->build($container);
        return $container;
    }
}
