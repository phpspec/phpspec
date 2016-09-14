<?php

namespace spec\PhpSpec\CodeGenerator\Generator;

use PhpSpec\CodeGenerator\Generator\Generator;
use PhpSpec\CodeGenerator\Generator\ImplementsGenerator;
use PhpSpec\Locator\Resource;
use PhpSpec\ObjectBehavior;

class ImplementsGeneratorSpec extends ObjectBehavior
{
    function it_is_initializable()
    {
        $this->shouldHaveType(ImplementsGenerator::class);
    }

    function it_is_a_generator()
    {
        $this->shouldHaveType(Generator::class);
    }

    function it_has_no_priority()
    {
        $this->getPriority()->shouldReturn(0);
    }

    function it_supports_implements_generation(Resource $resource)
    {
        $this->supports($resource, 'implements', [])->shouldReturn(true);
    }

    function it_does_not_support_other_generation(Resource $resource)
    {
        $this->supports($resource, 'method', [])->shouldReturn(false);
    }
}
