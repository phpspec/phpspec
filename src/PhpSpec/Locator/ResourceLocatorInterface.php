<?php

namespace PhpSpec\Locator;

interface ResourceLocatorInterface
{
    public function getAllResources();

    public function supportsQuery($query);
    public function findResources($query);

    public function supportsClass($classname);
    public function createResource($classname);

    public function getPriority();
}
