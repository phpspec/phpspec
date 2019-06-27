<?php

namespace spec\PhpSpec\CodeGenerator\Generator;

use PhpSpec\CodeGenerator\Generator\ValidateClassNameSpecificationGenerator;
use PhpSpec\CodeGenerator\Generator\Generator;
use PhpSpec\Console\ConsoleIO;
use PhpSpec\Locator\Resource;
use PhpSpec\ObjectBehavior;
use PhpSpec\Util\NameChecker;
use Prophecy\Argument;

class ValidateClassNameSpecificationGeneratorSpec extends ObjectBehavior
{

    function let(NameChecker $classNameChecker, ConsoleIO $io, Generator $originalGenerator)
    {
        $this->beConstructedWith($classNameChecker, $io, $originalGenerator);
    }

    function it_is_initializable()
    {
        $this->shouldHaveType(ValidateClassNameSpecificationGenerator::class);
    }

    function it_supports_generation_when_original_generator_supports_it(
        Generator $originalGenerator,
        Resource $resource
    ) {
        $originalGenerator->supports($resource, '', [])->willReturn(true);

        $this->supports($resource, '', [])->shouldReturn(true);
    }

    function it_does_not_support_generation_when_original_generator_doesnt(
        Generator $originalGenerator,
        Resource $resource
    ) {
        $originalGenerator->supports($resource, '', [])->willReturn(false);

        $this->supports($resource, '', [])->shouldReturn(false);
    }

    function it_delegates_generation_to_original_generator_for_valid_class_name(
        Generator $originalGenerator,
        Resource $resource,
        NameChecker $classNameChecker
    ) {
        $className = 'Acme\Markdown';
        $resource->getSrcClassname()->willReturn($className);
        $classNameChecker->isNameValid($className)->willReturn(true);

        $originalGenerator->generate($resource, [])->shouldBeCalled();

        $this->generate($resource, []);
    }

    function it_prints_error_and_skips_generation_for_invalid_class_name(
        Generator $originalGenerator,
        Resource $resource,
        NameChecker $classNameChecker,
        ConsoleIO $io
    ) {
        $className = 'Acme\Markdown';
        $resource->getSrcClassname()->willReturn($className);
        $classNameChecker->isNameValid($className)->willReturn(false);

        $io->writeBrokenCodeBlock(Argument::containingString('because class name contains reserved keyword'), 2)->shouldBeCalled();
        $originalGenerator->generate($resource, [])->shouldNotBeCalled();

        $this->generate($resource, []);
    }

}
