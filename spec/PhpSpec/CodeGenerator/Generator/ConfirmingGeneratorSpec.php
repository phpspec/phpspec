<?php

namespace spec\PhpSpec\CodeGenerator\Generator;

use PhpSpec\CodeGenerator\Generator\Generator;
use PhpSpec\Console\ConsoleIO;
use PhpSpec\ObjectBehavior;
use PhpSpec\Locator\Resource;

class ConfirmingGeneratorSpec extends ObjectBehavior
{
    function let(ConsoleIO $io, Generator $generator)
    {
        $this->beConstructedWith($io, 'Question for {CLASSNAME}', $generator);
    }

    function it_is_a_Generator()
    {
        $this->shouldHaveType('PhpSpec\CodeGenerator\Generator\Generator');
    }

    function it_supports_the_same_generator_as_its_parent(Generator $generator, Resource $resource)
    {
        $generator->supports($resource, 'generation', array())->willReturn(true);

        $this->supports($resource, 'generation', array())->shouldReturn(true);
    }

    function it_has_the_same_priority_as_its_parent(Generator $generator)
    {
        $generator->getPriority()->willReturn(1324);

        $this->getPriority()->shouldReturn(1324);
    }

    function it_does_not_call_the_parent_generate_method_if_the_user_answers_no(Generator $generator, Resource $resource, ConsoleIO $io)
    {
        $resource->getSrcClassname()->willReturn('Namespace/Classname');

        $io->askConfirmation('Question for Namespace/Classname')->willReturn(false);

        $this->generate($resource, array());

        $generator->generate($resource, array())->shouldNotHaveBeenCalled();
    }

    function it_calls_the_parent_generate_method_if_the_user_answers_yes(Generator $generator, Resource $resource, ConsoleIO $io)
    {
        $resource->getSrcClassname()->willReturn('Namespace/Classname');

        $io->askConfirmation('Question for Namespace/Classname')->willReturn(true);

        $this->generate($resource, array());

        $generator->generate($resource, array())->shouldHaveBeenCalled();
    }
}
