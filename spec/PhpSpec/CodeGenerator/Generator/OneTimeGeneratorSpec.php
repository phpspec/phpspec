<?php

namespace spec\PhpSpec\CodeGenerator\Generator;

use PhpSpec\CodeGenerator\Generator\GeneratorInterface;
use PhpSpec\ObjectBehavior;
use PhpSpec\Locator\ResourceInterface;

class OneTimeGeneratorSpec extends ObjectBehavior
{
    function let(GeneratorInterface $generator)
    {
        $this->beConstructedWith($generator);
    }

    function it_is_a_Generator()
    {
        $this->shouldHaveType('PhpSpec\CodeGenerator\Generator\GeneratorInterface');
    }

    function it_supports_the_same_generator_as_its_parent(GeneratorInterface $generator, ResourceInterface $resource)
    {
        $generator->supports($resource, 'generation', array())->willReturn(true);

        $this->supports($resource, 'generation', array())->shouldReturn(true);
    }

    function it_has_the_same_priority_as_its_parent(GeneratorInterface $generator)
    {
        $generator->getPriority()->willReturn(1324);

        $this->getPriority()->shouldReturn(1324);
    }

    function it_calls_the_parent_generate_method_just_once_for_the_same_classname(GeneratorInterface $generator, ResourceInterface $resource)
    {
        $resource->getSrcClassname()->willReturn('Namespace/Classname');

        $this->generate($resource, array());
        $this->generate($resource, array());

        $generator->generate($resource, array())->shouldHaveBeenCalledTimes(1);
    }

    function it_calls_the_parent_generate_method_once_per_each_classname(GeneratorInterface $generator, ResourceInterface $resource)
    {
        $resource->getSrcClassname()->willReturn('Namespace/Classname1', 'Namespace/Classname2');

        $this->generate($resource, array());
        $this->generate($resource, array());

        $generator->generate($resource, array())->shouldHaveBeenCalledTimes(2);
    }
}
