<?php

namespace spec\PhpSpec\Locator;

use PhpSpec\Config\OptionsConfig;
use PhpSpec\ObjectBehavior;

use PhpSpec\Locator\ResourceLocator;
use PhpSpec\Locator\Resource;
use PhpSpec\Locator\Factory as LocatorFactory;
use PhpSpec\Config\Manager as ConfigManager;

class PrioritizedResourceManagerSpec extends ObjectBehavior
{
    function let(
        ResourceLocator $locator1,
        ResourceLocator $locator2,
        LocatorFactory  $locatorFactory,
        ConfigManager   $configManager,
        OptionsConfig   $optionsConfig
    ) {
        $this->beConstructedWith($locatorFactory, $configManager);

        $configManager->optionsConfig()->willReturn($optionsConfig);
        $optionsConfig->getSuites()->willReturn(['a', 'b']);

        $locatorFactory->buildLocatorForSuite('a')->willReturn($locator1);
        $locatorFactory->buildLocatorForSuite('b')->willReturn($locator2);

        $locator1->getPriority()->willReturn(5);
        $locator2->getPriority()->willReturn(10);
    }

    function it_locates_resources_using_all_registered_locators($locator1, $locator2,
        Resource $resource1, Resource $resource2, Resource $resource3
    ) {
        $locator1->supportsQuery('s:query')->willReturn(true);
        $locator1->findResources('s:query')->willReturn(array($resource3, $resource2));
        $locator2->supportsQuery('s:query')->willReturn(true);
        $locator2->findResources('s:query')->willReturn(array($resource1));

        $resource1->getSpecClassname()->willReturn('Some\Spec1');
        $resource2->getSpecClassname()->willReturn('Some\Spec2');
        $resource3->getSpecClassname()->willReturn('Some\Spec3');

        $this->locateResources('s:query')->shouldReturn(array($resource1, $resource3, $resource2));
    }

    function it_locates_all_locators_resources_if_query_string_is_empty($locator1, $locator2,
        Resource $resource1, Resource $resource2, Resource $resource3
    ) {
        $locator1->getAllResources()->willReturn(array($resource3, $resource2));
        $locator2->getAllResources()->willReturn(array($resource1));

        $resource1->getSpecClassname()->willReturn('Some\Spec1');
        $resource2->getSpecClassname()->willReturn('Some\Spec2');
        $resource3->getSpecClassname()->willReturn('Some\Spec3');

        $this->locateResources('')->shouldReturn(array($resource1, $resource3, $resource2));
    }

    function it_returns_empty_array_if_registered_locators_do_not_support_query($locator1, $locator2)
    {
        $locator1->supportsQuery('s:query')->willReturn(false);
        $locator1->findResources('s:query')->shouldNotBeCalled();

        $locator2->supportsQuery('s:query')->willReturn(false);
        $locator2->findResources('s:query')->shouldNotBeCalled();

        $this->locateResources('s:query')->shouldReturn(array());
    }

    function it_creates_resource_from_classname_using_locator_with_highest_priority(
        $locator1, $locator2, Resource $resource1, Resource $resource2
    ) {
        $locator1->supportsClass('Some\Class')->willReturn(true);
        $locator1->createResource('Some\Class')->willReturn($resource1);
        $locator2->supportsClass('Some\Class')->willReturn(true);
        $locator2->createResource('Some\Class')->willReturn($resource2);

        $this->createResource('Some\Class')->shouldReturn($resource2);
    }

    function it_throws_an_exception_if_locators_do_not_support_classname($locator1)
    {
        $locator1->supportsClass('Some\Class')->willReturn(false);

        $this->shouldThrow('RuntimeException')->duringCreateResource('Some\Class');
    }

    function it_does_not_allow_two_resources_for_the_same_spec(
        $locator1, $locator2, Resource $resource1, Resource $resource2
    ) {
        $resource1->getSpecClassname()->willReturn('Some\Spec');
        $resource2->getSpecClassname()->willReturn('Some\Spec');

        $locator1->getAllResources()->willReturn(array($resource1));
        $locator2->getAllResources()->willReturn(array($resource2));

        $this->locateResources('')->shouldReturn(array($resource2));
    }

    function it_uses_the_resource_from_the_highest_priority_locator_when_duplicates_occur(
        $locator1, $locator2, Resource $resource1, Resource $resource2
    ) {
        $locator1->getPriority()->willReturn(2);
        $locator2->getPriority()->willReturn(1);

        $resource1->getSpecClassname()->willReturn('Some\Spec');
        $resource2->getSpecClassname()->willReturn('Some\Spec');

        $locator1->getAllResources()->willReturn(array($resource1));
        $locator2->getAllResources()->willReturn(array($resource2));

        $this->locateResources('')->shouldReturn(array($resource1));
    }
}
