<?php

namespace PhpSpec\Extension;

use PhpSpec\ServiceContainer;

interface ExtensionInterface
{
    public function load(ServiceContainer $container);
}
