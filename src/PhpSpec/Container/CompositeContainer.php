<?php

namespace PhpSpec\Container;

use PhpSpec\Container\ServiceContainer\ConfigObject;
use PhpSpec\Container\ServiceContainer\LocatorConfiguredMidExecution;
use PhpSpec\Container\ServiceContainer\Registry;
use PhpSpec\Container\ServiceContainer\ServiceLocator;
use Interop\Container\ContainerInterface;
use UltraLite\Container\CompositeContainer as UltraliteCompositeContainer;

class CompositeContainer extends UltraliteCompositeContainer
{
    private $container;

    public function setPhpSpecContainer(ServiceContainer $container)
    {
        $this->container = $container;
    }

    /**
     * @return Registry
     */
    public function getRegistry()
    {
        return $this->container;
    }

    /**
     * @return ServiceLocator
     */
    public function getServiceLocator()
    {
        return $this->container;
    }

    /**
     * @return LocatorConfiguredMidExecution
     */
    public function getLocatorConfiguredMidExecution()
    {
        return $this->container;
    }

    /**
     * @return ConfigObject
     */
    public function getConfigObject()
    {
        return $this->container;
    }

    /**
     * @return ContainerInterface
     */
    public function getContainerPassedToExtensions()
    {
        return $this;
    }
}
