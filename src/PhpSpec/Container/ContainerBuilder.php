<?php

namespace PhpSpec\Container;

use PhpSpec\Container\ServiceProvider\ServiceProvider;
use PhpSpec\Container\ServiceContainer;

class ContainerBuilder
{
    /**
     * @var CompositeContainer
     */
    private $compositeContainer;

    public function buildContainer()
    {
        $this->compositeContainer = new CompositeContainer();

        $container = new ServiceContainer();
        $this->compositeContainer->setPhpSpecContainer($container);
        $containerConfig = new ServiceProvider();
        $containerConfig->build($container);

        $container->setCompositeContainer($this->compositeContainer);
        $this->compositeContainer->addContainer($container);

        return $this->compositeContainer;
    }
}
