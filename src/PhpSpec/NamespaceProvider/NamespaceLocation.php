<?php

namespace PhpSpec\NamespaceProvider;

final class NamespaceLocation
{
    public function __construct(private string $namespace, private string $location, private string $autoloadingStandard)
    {
    }

    public function getNamespace(): string
    {
        return $this->namespace;
    }

    public function getLocation(): string
    {
        return $this->location;
    }

    public function getAutoloadingStandard(): string
    {
        return $this->autoloadingStandard;
    }
}
