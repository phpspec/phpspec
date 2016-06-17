<?php

namespace PhpSpec\Container;

use PhpSpec\Container\ServiceProvider\ServiceProvider;
use PhpSpec\Container\ServiceContainer;

class ContainerBuilder
{
    /**
     * @return CompositeContainer
     */
    public function buildContainer()
    {
        $compositeContainer = new CompositeContainer();

        $container = new ServiceContainer();
        $compositeContainer->setPhpSpecContainer($container);
        $containerConfig = new ServiceProvider();
        $containerConfig->build($container);

        $container->setCompositeContainer($compositeContainer);
        $compositeContainer->addContainer($container);

        return $compositeContainer;
    }
}
