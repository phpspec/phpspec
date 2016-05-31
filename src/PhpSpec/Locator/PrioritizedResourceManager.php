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
use PhpSpec\Locator\Factory as LocatorFactory;
use PhpSpec\Config\Manager as ConfigManager;

final class PrioritizedResourceManager implements ResourceManager
{
    /**
     * @var ResourceLocator[]
     */
    private $locators = array();

    /**
     * @var LocatorFactory
     */
    private $locatorFactory;

    /**
     * @var ConfigManager
     */
    private $configManager;

    public function __construct(LocatorFactory $locatorFactory, ConfigManager $configManager)
    {
        $this->locatorFactory = $locatorFactory;
        $this->configManager = $configManager;
    }

    private function registerLocators()
    {
        foreach ($this->configManager->optionsConfig()->getSuites() as $suite) {
            $this->locators[] = $this->locatorFactory->buildLocatorForSuite($suite);
        }

        @usort($this->locators, function (ResourceLocator $locator1, ResourceLocator $locator2) {
            return $locator2->getPriority() - $locator1->getPriority();
        });
    }

    /**
     * @param string $query
     *
     * @return Resource[]
     */
    public function locateResources($query)
    {
        if (empty($this->locators)) {
            $this->registerLocators();
        }

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
     * @param string $classname
     *
     * @return Resource
     *
     * @throws \RuntimeException
     */
    public function createResource($classname)
    {
        if (empty($this->locators)) {
            $this->registerLocators();
        }

        foreach ($this->locators as $locator) {
            if ($locator->supportsClass($classname)) {
                return $locator->createResource($classname);
            }
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
}
