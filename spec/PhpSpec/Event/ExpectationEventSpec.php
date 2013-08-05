<?php

namespace spec\PhpSpec\Event;

use PhpSpec\ObjectBehavior;
use PhpSpec\Loader\Suite;
use PhpSpec\Loader\Node\SpecificationNode;
use PhpSpec\Loader\Node\ExampleNode;
use PhpSpec\Matcher\MatcherInterface;
use Prophecy\Argument;

class ExpectationEventSpec extends ObjectBehavior
{
    function let(Suite $suite, SpecificationNode $specification, ExampleNode $example, MatcherInterface $matcher)
    {
        $this->beConstructedWith($example, $matcher);

        $example->getSpecification()->willReturn($specification);
        $specification->getSuite()->willReturn($suite);
    }

    function it_is_an_event()
    {
        $this->shouldBeAnInstanceOf('Symfony\Component\EventDispatcher\Event');
        $this->shouldBeAnInstanceOf('PhpSpec\Event\EventInterface');
    }

    function it_provides_a_link_to_matcher($matcher)
    {
        $this->getMatcher()->shouldReturn($matcher);
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
}
