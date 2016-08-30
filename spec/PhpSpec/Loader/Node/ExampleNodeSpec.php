<?php

namespace spec\PhpSpec\Loader\Node;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

use PhpSpec\Loader\Node\SpecificationNode;

use ReflectionFunctionAbstract;

class ExampleNodeSpec extends ObjectBehavior
{
    function let(ReflectionFunctionAbstract $function)
    {
        $function->isClosure()->willReturn(false);
        $this->beConstructedWith('example node', $function);
    }

    function it_provides_a_link_to_title()
    {
        $this->getTitle()->shouldReturn('example node');
    }

    function it_provides_a_link_to_function($function)
    {
        $this->getFunctionReflection()->shouldReturn($function);
    }

    function it_provides_a_link_to_specification(SpecificationNode $specification)
    {
        $this->setSpecification($specification);
        $this->getSpecification()->shouldReturn($specification);
    }

    function it_is_not_pending_by_default()
    {
        $this->isPending()->shouldReturn(false);
    }

    function it_is_pending_after_marked_as_pending_with_no_args()
    {
        $this->markAsPending();
        $this->isPending()->shouldReturn(true);
    }

    function it_is_pending_after_marked_as_pending_with_true()
    {
        $this->markAsPending(true);
        $this->isPending()->shouldReturn(true);
    }

    function it_is_not_pending_after_marked_as_pending_with_false()
    {
        $this->markAsPending(false);
        $this->isPending()->shouldReturn(false);
    }

    function it_returns_its_line_number(\ReflectionFunctionAbstract $function)
    {
        $function->getStartLine()->willReturn(100);

        $this->getLineNumber()->shouldReturn(100);
    }

    function it_returns_its_line_number_as_zero_if_constructed_with_closure(
        \ReflectionFunctionAbstract $function
    )
    {
        $function->isClosure()->willReturn(true);

        $this->getLineNumber()->shouldReturn(0);
    }
}
