<?php

namespace spec\PhpSpec\Event;

use PhpSpec\ObjectBehavior;

use PhpSpec\Event\ExampleEvent as Example;
use PhpSpec\Loader\Node\SpecificationNode;

class SuiteEventSpec extends ObjectBehavior
{
    function let()
    {
        $this->beConstructedWith(10, Example::FAILED);
    }

    function it_is_an_event()
    {
        $this->shouldBeAnInstanceOf('Symfony\Component\EventDispatcher\Event');
        $this->shouldBeAnInstanceOf('PhpSpec\Event\EventInterface');
    }

    function it_provides_a_link_to_time()
    {
        $this->getTime()->shouldReturn(10);
    }

    function it_provides_a_link_to_result()
    {
        $this->getResult()->shouldReturn(Example::FAILED);
    }
}
