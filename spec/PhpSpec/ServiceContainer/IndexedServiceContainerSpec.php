<?php

namespace spec\PhpSpec\ServiceContainer;

use PhpSpec\ObjectBehavior;
use stdClass;

class IndexedServiceContainerSpec extends ObjectBehavior
{
    function it_stores_parameters()
    {
        $this->setParam('some_param', 42);
        $this->getParam('some_param')->shouldReturn(42);
    }

    function it_returns_null_value_for_unexisting_parameter()
    {
        $this->getParam('unexisting')->shouldReturn(null);
    }

    function it_returns_custom_default_for_unexisting_parameter_if_provided()
    {
        $this->getParam('unexisting', 42)->shouldReturn(42);
    }

    function it_stores_services($service)
    {
        $this->set('some_service', $service);
        $this->get('some_service')->shouldReturn($service);
    }

    function it_knows_when_services_are_not_defined()
    {
        $this->has('some_service')->shouldReturn(false);
    }

    function it_knows_when_services_are_defined($service)
    {
        $this->set('some_service', $service);
        $this->has('some_service')->shouldReturn(true);
    }

    function it_returns_nothing_when_no_services_are_tagged()
    {
        $this->getByTag('some_tag')->shouldReturn([]);
    }

    function it_returns_services_which_are_set_using_tags($service)
    {
        $obj = new \StdClass();
        $this->set('some_service', $obj, ['some_tag']);
        $this->getByTag('some_tag')->shouldReturn([$obj]);
    }

    function it_returns_services_which_are_defined_using_tags()
    {
        $obj = new \StdClass();
        $this->define('some_service', function () use ($obj) { return $obj; }, ['some_tag']);
        $this->getByTag('some_tag')->shouldReturn([$obj]);
    }

    function it_throws_exception_when_trying_to_get_unexisting_service()
    {
        $this->shouldThrow('InvalidArgumentException')->duringGet('unexisting');
    }

    function it_returns_same_object_when_factory_is_invoked_multiple_times()
    {
        $this->define('random_object', function (){ return new stdClass(); });
        $object1 = $this->get('random_object');
        $object2 = $this->get('random_object');

        $object2->shouldBe($object1);
    }

    function it_uses_new_definition_when_a_service_is_redefined()
    {
        $this->define('some_service', function () { return new stdClass(); });
        $object1 = $this->get('some_service');


        $this->define('some_service', function () { return new stdClass(); });

        $object2 = $this->get('some_service');

        $object1->shouldNotBe($object2);
    }


    function it_does_not_evaluate_callables_that_are_set()
    {
        $this->set('some_service', function(){ return 100; });
        $this->get('some_service')->shouldNotBe(100);
    }

    function it_provides_a_way_to_remove_service_by_key($service)
    {
        $this->set('collection1.some_service', $service);
        $this->remove('collection1.some_service');

        $this->shouldThrow()->duringGet('collection1.some_service');
    }

    function it_supports_custom_service_configurators()
    {
        $this->addConfigurator(function ($c) {
            $c->setParam('name', 'Jim');
        });
        $this->configure();

        $this->getParam('name')->shouldReturn('Jim');
    }
}
