<?php

namespace PhpSpec\Locator;

use RuntimeException;

class ResourceManager
{
    private $locators = array();

    public function registerLocator(ResourceLocatorInterface $locator)
    {
        $this->locators[] = $locator;

        @usort($this->locators, function($locator1, $locator2) {
            return $locator2->getPriority() - $locator1->getPriority();
        });
    }

    public function locateResources($query)
    {
        $resources = array();
        foreach ($this->locators as $locator) {
            if (empty($query)) {
                $resources = array_merge($resources, $locator->getAllResources());
                continue;
            }

            if (!$locator->supportsQuery($query)) {
                continue;
            }

            $resources = array_merge($resources, $locator->findResources($query));
        }

        return array_values($resources);
    }

    public function createResource($classname)
    {
        foreach ($this->locators as $locator) {
            if ($locator->supportsClass($classname)) {
                return $locator->createResource($classname);
            }
        }

        throw new RuntimeException(sprintf(
            'Can not find appropriate suite scope for class `%s`.', $classname
        ));
    }
}
