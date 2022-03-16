<?php

namespace PhpSpec\NamespaceProvider;

final class NamespaceLocation
{
    private string $namespace;
    private string $location;
    private string $autoloadingStandard;

    public function __construct(string $namespace, string $location, string $autoloadingStandard)
    {
        $this->namespace = $namespace;
        $this->location = $location;
        $this->autoloadingStandard = $autoloadingStandard;
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
