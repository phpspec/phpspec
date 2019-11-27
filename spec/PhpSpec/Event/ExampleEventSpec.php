<?php

namespace spec\PhpSpec\Event;

use PhpSpec\ObjectBehavior;

use PhpSpec\Loader\Node\ExampleNode;
use PhpSpec\Loader\Node\SpecificationNode;
use PhpSpec\Loader\Suite;

use Exception;

class ExampleEventSpec extends ObjectBehavior
{
    function let(Suite $suite, SpecificationNode $specification, ExampleNode $example, Exception $exception)
    {
        $this->beConstructedWith($example, 10, $this->FAILED, $exception);

        $example->getSpecification()->willReturn($specification);
        $specification->getSuite()->willReturn($suite);
    }

    function it_is_an_event()
    {
        $this->shouldBeAnInstanceOf('PhpSpec\Event\BaseEvent');
        $this->shouldBeAnInstanceOf('PhpSpec\Event\PhpSpecEvent');
    }

    function it_provides_a_link_to_example($example)
    {
        $this->getExample()->shouldReturn($example);
    }

    function it_provides_a_link_to_specification($specification)
    {
        $this->getSpecification()->shouldReturn($specification);
    }

    function it_provides_a_link_to_suite($suite)
    {
        $this->getSuite()->shouldReturn($suite);
    }

    function it_provides_a_link_to_time()
    {
        $this->getTime()->shouldReturn((double)10.0);
    }

    function it_provides_a_link_to_result()
    {
        $this->getResult()->shouldReturn($this->FAILED);
    }

    function it_provides_a_link_to_exception($exception)
    {
        $this->getException()->shouldReturn($exception);
    }

    function it_initializes_a_default_result(ExampleNode $example)
    {
        $this->beConstructedWith($example);

        $this->getResult()->shouldReturn($this->PASSED);
    }

    function it_initializes_a_default_time(ExampleNode $example)
    {
        $this->beConstructedWith($example);

        $this->getTime()->shouldReturn((double)0.0);
    }
}
