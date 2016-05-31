<?php

namespace spec\PhpSpec\Container;

use Interop\Container\ContainerInterface;
use PhpSpec\ObjectBehavior;

class ServiceContainerSpec extends ObjectBehavior
{
    function it_is_standards_compliant()
    {
        $this->shouldHaveType(ContainerInterface::class);
    }

    function it_stores_services($service)
    {
        $this->set('some_service', $service);
        $this->get('some_service')->shouldReturn($service);
    }

    function it_throws_exception_when_trying_to_get_unexisting_service()
    {
        $this->shouldThrow('InvalidArgumentException')->duringGet('unexisting');
    }

    function it_evaluates_factory_function_set_as_service()
    {
        $this->set('random_number', function () { return rand(); });
        $number1 = $this->get('random_number');
        $number2 = $this->get('random_number');

        $number1->shouldBeInteger();
        $number2->shouldBeInteger();

        $number2->shouldNotBe($number1);
    }

    function it_evaluates_factory_function_only_once_for_shared_services()
    {
        $this->setShared('random_number', function () { return rand(); });
        $number1 = $this->get('random_number');
        $number2 = $this->get('random_number');

        $number2->shouldBe($number1);
    }

    function it_provides_a_way_to_retrieve_services_by_prefix($service1, $service2, $service3)
    {
        $this->set('collection1.serv1', $service1);
        $this->set('collection1.serv2', $service2);
        $this->set('collection2.serv3', $service3);

        $this->getByPrefix('collection1')->shouldReturn(array($service1, $service2));
    }
}
