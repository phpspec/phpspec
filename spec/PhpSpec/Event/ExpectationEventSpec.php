<?php

namespace spec\PhpSpec\Event;

use PhpSpec\ObjectBehavior;
use PhpSpec\Loader\Suite;
use PhpSpec\Loader\Node\SpecificationNode;
use PhpSpec\Loader\Node\ExampleNode;
use PhpSpec\Matcher\Matcher;
use Exception;

class ExpectationEventSpec extends ObjectBehavior
{
    function let(Suite $suite, SpecificationNode $specification, ExampleNode $example,
                 Matcher $matcher, $subject, Exception $exception)
    {
        $method = 'calledMethod';
        $arguments = array('methodArguments');

        $this->beConstructedWith($example, $matcher, $subject, $method, $arguments, $this->FAILED, $exception);

        $example->getSpecification()->willReturn($specification);
        $specification->getSuite()->willReturn($suite);
    }

    function it_is_an_event()
    {
        $this->shouldBeAnInstanceOf('PhpSpec\Event\BaseEvent');
        $this->shouldBeAnInstanceOf('PhpSpec\Event\PhpSpecEvent');
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

    function it_provides_a_link_to_subject($subject)
    {
        $this->getSubject()->shouldReturn($subject);
    }

    function it_provides_a_link_to_method()
    {
        $this->getMethod()->shouldReturn('calledMethod');
    }

    function it_provides_a_link_to_arguments()
    {
        $this->getArguments()->shouldReturn(array('methodArguments'));
    }

    function it_provides_a_link_to_result()
    {
        $this->getResult()->shouldReturn($this->FAILED);
    }

    function it_provides_a_link_to_exception($exception)
    {
        $this->getException()->shouldReturn($exception);
    }

    function it_initializes_a_default_result(ExampleNode $example, Matcher $matcher, $subject)
    {
        $method = 'calledMethod';
        $arguments = array('methodArguments');

        $this->beConstructedWith($example, $matcher, $subject, $method, $arguments);

        $this->getResult()->shouldReturn($this->PASSED);
    }
}
