<?php

namespace PhpSpec\Container\ServiceContainer;

interface ContainerPassedToExtensions extends ConfigObject, Registry, ServiceLocator, LocatorConfiguredMidExecution
{
}