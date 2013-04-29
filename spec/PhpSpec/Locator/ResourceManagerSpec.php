<?php

namespace spec\PhpSpec\Locator;

use PhpSpec\ObjectBehavior;

use PhpSpec\Locator\ResourceLocatorInterface;
use PhpSpec\Locator\ResourceInterface;

class ResourceManagerSpec extends ObjectBehavior
{
    function let(ResourceLocatorInterface $locator1, ResourceLocatorInterface $locator2)
    {
        $locator1->getPriority()->willReturn(5);
        $locator2->getPriority()->willReturn(10);
    }

    function it_locates_resources_using_all_registered_locators($locator1, $locator2,
        ResourceInterface $resource1, ResourceInterface $resource2, ResourceInterface $resource3
    )
    {
        $this->registerLocator($locator1);
        $this->registerLocator($locator2);

        $locator1->supportsQuery('s:query')->willReturn(true);
        $locator1->findResources('s:query')->willReturn(array($resource3, $resource2));
        $locator2->supportsQuery('s:query')->willReturn(true);
        $locator2->findResources('s:query')->willReturn(array($resource1));

        $this->locateResources('s:query')->shouldReturn(array($resource1, $resource3, $resource2));
    }

    function it_locates_all_locators_resources_if_query_string_is_empty($locator1, $locator2,
        ResourceInterface $resource1, ResourceInterface $resource2, ResourceInterface $resource3
    )
    {
        $this->registerLocator($locator1);
        $this->registerLocator($locator2);

        $locator1->getAllResources()->willReturn(array($resource3, $resource2));
        $locator2->getAllResources()->willReturn(array($resource1));

        $this->locateResources('')->shouldReturn(array($resource1, $resource3, $resource2));
    }

    function it_returns_empty_array_if_registered_locators_do_not_support_query($locator1)
    {
        $this->registerLocator($locator1);

        $locator1->supportsQuery('s:query')->willReturn(false);
        $locator1->findResources('s:query')->shouldNotBeCalled();

        $this->locateResources('s:query')->shouldReturn(array());
    }

    function it_creates_resource_from_classname_using_locator_with_highest_priority(
        $locator1, $locator2, ResourceInterface $resource1, ResourceInterface $resource2
    )
    {
        $this->registerLocator($locator1);
        $this->registerLocator($locator2);

        $locator1->supportsClass('Some\Class')->willReturn(true);
        $locator1->createResource('Some\Class')->willReturn($resource1);
        $locator2->supportsClass('Some\Class')->willReturn(true);
        $locator2->createResource('Some\Class')->willReturn($resource2);

        $this->createResource('Some\Class')->shouldReturn($resource2);
    }

    function it_throws_an_exception_if_locators_do_not_support_classname($locator1)
    {
        $this->registerLocator($locator1);

        $locator1->supportsClass('Some\Class')->willReturn(false);

        $this->shouldThrow('RuntimeException')->duringCreateResource('Some\Class');
    }
}
