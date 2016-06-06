<?php

namespace PhpSpec\Container\ServiceContainer;

interface LocatorConfiguredMidExecution extends ServiceLocator
{
    public function configure();
}
