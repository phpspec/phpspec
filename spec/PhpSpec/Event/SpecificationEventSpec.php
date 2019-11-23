<?php

namespace spec\PhpSpec\Event;

use PhpSpec\ObjectBehavior;

use PhpSpec\Event\ExampleEvent as Example;
use PhpSpec\Loader\Node\SpecificationNode;
use PhpSpec\Loader\Suite;

class SpecificationEventSpec extends ObjectBehavior
{
    function let(Suite $suite, SpecificationNode $specification)
    {
        $this->beConstructedWith($specification, 10, Example::FAILED);

        $specification->getSuite()->willReturn($suite);
    }

    function it_is_an_event()
    {
        $this->shouldBeAnInstanceOf('PhpSpec\Event\BaseEvent');
        $this->shouldBeAnInstanceOf('PhpSpec\Event\PhpSpecEvent');
    }

    function it_provides_a_link_to_suite($suite)
    {
        $this->getSuite()->shouldReturn($suite);
    }

    function it_provides_a_link_to_specification($specification)
    {
        $this->getSpecification()->shouldReturn($specification);
    }

    function it_provides_a_link_to_time()
    {
        $this->getTime()->shouldReturn(10.0);
    }

    function it_provides_a_link_to_result()
    {
        $this->getResult()->shouldReturn(Example::FAILED);
    }

    function it_initializes_a_default_result(SpecificationNode $specification)
    {
        $this->beConstructedWith($specification);

        $this->getResult()->shouldReturn(Example::PASSED);
    }

    function it_initializes_a_default_time(SpecificationNode $specification)
    {
        $this->beConstructedWith($specification);

        $this->getTime()->shouldReturn((double) 0.0);
    }
}
