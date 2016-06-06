<?php

namespace PhpSpec\Container;

use PhpSpec\Console\ContainerAssembler;
use PhpSpec\Container\ServiceContainer;
use PhpSpec\Container\ServiceContainer\Registry;
use PhpSpec\Container\ServiceContainer\ServiceLocator;
use PhpSpec\Container\ServiceContainer\LocatorConfiguredMidExecution;
use PhpSpec\Container\ServiceContainer\ConfigObject;
use PhpSpec\Container\ServiceContainer\ContainerPassedToExtensions;

class ContainerBuilder
{
    /**
     * @var ServiceContainer
     */
    private $container;

    public function buildContainer()
    {
        $this->container = new ServiceContainer();
        $containerConfig = new ContainerAssembler();
        $containerConfig->build($this->container);
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
     * @return ContainerPassedToExtensions
     */
    public function getContainerPassedToExtensions()
    {
        return $this->container;
    }
}
