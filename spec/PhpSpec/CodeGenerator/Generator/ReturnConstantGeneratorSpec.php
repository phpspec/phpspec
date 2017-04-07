<?php

namespace spec\PhpSpec\CodeGenerator\Generator;

use PhpSpec\CodeGenerator\TemplateRenderer;
use PhpSpec\Console\ConsoleIO;
use PhpSpec\ObjectBehavior;
use PhpSpec\Util\Filesystem;
use Prophecy\Argument;
use PhpSpec\Locator\Resource;

class ReturnConstantGeneratorSpec extends ObjectBehavior
{
    function let(ConsoleIO $io, TemplateRenderer $templates, Filesystem $filesystem)
    {
        $this->beConstructedWith($io, $templates, $filesystem);
    }

    function it_is_a_generator()
    {
        $this->shouldHaveType('PhpSpec\CodeGenerator\Generator\Generator');
    }

    function it_supports_returnConstant_generation(Resource $resource)
    {
        $this->supports($resource, 'returnConstant', array())->shouldReturn(true);
    }

    function it_does_not_support_anything_else(Resource $resource)
    {
        $this->supports($resource, 'anything_else', array())->shouldReturn(false);
    }

    function its_priority_is_0()
    {
        $this->getPriority()->shouldReturn(0);
    }
}
