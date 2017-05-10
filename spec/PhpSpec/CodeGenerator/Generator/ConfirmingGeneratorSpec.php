<?php

namespace spec\PhpSpec\CodeGenerator\Generator;

use PhpSpec\CodeGenerator\Generator\GeneratorInterface;
use PhpSpec\Console\IO;
use PhpSpec\ObjectBehavior;
use PhpSpec\Locator\ResourceInterface;

class ConfirmingGeneratorSpec extends ObjectBehavior
{
    function let(IO $io, GeneratorInterface $generator)
    {
        $this->beConstructedWith($io, 'Question for {CLASSNAME}', $generator);
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

    function it_does_not_call_the_parent_generate_method_if_the_user_answers_no(GeneratorInterface $generator, ResourceInterface $resource, IO $io)
    {
        $resource->getSrcClassname()->willReturn('Namespace/Classname');

        $io->askConfirmation('Question for Namespace/Classname')->willReturn(false);

        $this->generate($resource, array());

        $generator->generate($resource, array())->shouldNotHaveBeenCalled();
    }

    function it_calls_the_parent_generate_method_if_the_user_answers_yes(GeneratorInterface $generator, ResourceInterface $resource, IO $io)
    {
        $resource->getSrcClassname()->willReturn('Namespace/Classname');

        $io->askConfirmation('Question for Namespace/Classname')->willReturn(true);

        $this->generate($resource, array());

        $generator->generate($resource, array())->shouldHaveBeenCalled();
    }
}
