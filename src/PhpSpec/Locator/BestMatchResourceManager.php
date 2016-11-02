<?php

/*
 * This file is part of PhpSpec, A php toolset to drive emergent
 * design by specification.
 *
 * (c) Marcello Duarte <marcello.duarte@gmail.com>
 * (c) Konstantin Kudryashov <ever.zet@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpSpec\Locator;

use PhpSpec\Exception\Locator\ResourceCreationException;

final class BestMatchResourceManager implements ResourceManager
{
    /**
     * @var ResourceLocator[]
     */
    private $locators = array();

    /**
     * @param ResourceLocator $locator
     */
    public function registerLocator(ResourceLocator $locator)
    {
        $this->locators[] = $locator;

        @usort($this->locators, function (ResourceLocator $locator1, ResourceLocator $locator2) {
            return $locator2->getPriority() - $locator1->getPriority();
        });
    }

    /**
     * @inheritDoc
     */
    public function locateResources(string $query)
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

        return $this->removeDuplicateResources($resources);
    }

    /**
     * @inheritDoc
     */
    public function createResource(string $classname): \PhpSpec\Locator\Resource
    {
        $matchedLocators = $this->sortAndGroupLocatorsByMatchScore($classname);
        $bestMatchedLocators = $this->removeDuplicateLocators(reset($matchedLocators));
        if (count($bestMatchedLocators) === 1) {
            $locator = array_pop($bestMatchedLocators);
            return $locator->createResource($classname);
        }

        throw new ResourceCreationException(
            sprintf(
                'Can not find appropriate suite scope for class `%s`.',
                $classname
            )
        );
    }

    /**
     * @param array $resources
     *
     * @return Resource[]
     */
    private function removeDuplicateResources(array $resources)
    {
        $filteredResources = array();

        foreach ($resources as $resource) {
            if (!array_key_exists($resource->getSpecClassname(), $filteredResources)) {
                $filteredResources[$resource->getSpecClassname()] = $resource;
            }
        }

        return array_values($filteredResources);
    }

    /**
     * @param  string $classname
     *
     * @return array
     */
    private function sortAndGroupLocatorsByMatchScore($classname)
    {
        $matchedLocators = [];
        foreach ($this->locators as $locator) {
            if (($matchScore = $locator->calculateMatchScore($classname)) > 0) {
                $matchedLocators[$matchScore][] = $locator;
            }
        }
        krsort($matchedLocators);
        return $matchedLocators;
    }

    /**
     * @param  ResourceLocator[] $locators
     *
     * @return array
     */
    private function removeDuplicateLocators(array $locators)
    {
        return array_unique($locators, SORT_REGULAR);
    }
}
