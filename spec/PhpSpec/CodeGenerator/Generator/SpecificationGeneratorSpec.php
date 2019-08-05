<?php

namespace spec\PhpSpec\CodeGenerator\Generator;

use PhpSpec\ObjectBehavior;
use PhpSpec\Process\Context\ExecutionContext;
use Prophecy\Argument;

use PhpSpec\Console\ConsoleIO;
use PhpSpec\CodeGenerator\TemplateRenderer;
use PhpSpec\Util\Filesystem;
use PhpSpec\Locator\Resource;

class SpecificationGeneratorSpec extends ObjectBehavior
{
    function let(ConsoleIO $io, TemplateRenderer $tpl, Filesystem $fs, ExecutionContext $context)
    {
        $this->beConstructedWith($io, $tpl, $fs, $context);
    }

    function it_is_a_generator()
    {
        $this->shouldBeAnInstanceOf('PhpSpec\CodeGenerator\Generator\Generator');
    }

    function it_supports_specification_generations(Resource $resource)
    {
        $this->supports($resource, 'specification', array())->shouldReturn(true);
    }

    function it_does_not_support_anything_else(Resource $resource)
    {
        $this->supports($resource, 'anything_else', array())->shouldReturn(false);
    }

    function its_priority_is_0()
    {
        $this->getPriority()->shouldReturn(0);
    }

    function it_generates_spec_class_from_resource_and_puts_it_into_appropriate_folder(
        $io, $tpl, $fs, Resource $resource
    ) {
        $resource->getSpecName()->willReturn('AppSpec');
        $resource->getSpecFilename()->willReturn('/project/spec/Acme/AppSpec.php');
        $resource->getSpecNamespace()->willReturn('spec\Acme');
        $resource->getSrcClassname()->willReturn('Acme\App');
        $resource->getName()->willReturn('App');

        $values = array(
            '%filepath%'  => '/project/spec/Acme/AppSpec.php',
            '%name%'      => 'AppSpec',
            '%namespace%' => 'spec\Acme',
            '%imports%'   => "use Acme\App;\nuse PhpSpec\ObjectBehavior;",
            '%subject%'   => 'Acme\App',
            '%subject_class%'  => 'App',
        );

        $tpl->render('specification', $values)->willReturn('');
        $tpl->renderString(Argument::type('string'), $values)->willReturn('generated code');

        $fs->pathExists('/project/spec/Acme/AppSpec.php')->willReturn(false);
        $fs->isDirectory('/project/spec/Acme')->willReturn(true);
        $fs->putFileContents('/project/spec/Acme/AppSpec.php', 'generated code')->shouldBeCalled();

        $this->generate($resource);
    }

    function it_uses_template_provided_by_templating_system_if_there_is_one(
        $io, $tpl, $fs, Resource $resource
    ) {
        $resource->getSpecName()->willReturn('AppSpec');
        $resource->getSpecFilename()->willReturn('/project/spec/Acme/AppSpec.php');
        $resource->getSpecNamespace()->willReturn('spec\Acme');
        $resource->getSrcClassname()->willReturn('Acme\App');
        $resource->getName()->willReturn('App');

        $values = array(
            '%filepath%'  => '/project/spec/Acme/AppSpec.php',
            '%name%'      => 'AppSpec',
            '%namespace%' => 'spec\Acme',
            '%imports%'   => "use Acme\App;\nuse PhpSpec\ObjectBehavior;",
            '%subject%'   => 'Acme\App',
            '%subject_class%'  => 'App',
        );

        $tpl->render('specification', $values)->willReturn('template code');
        $tpl->renderString(Argument::type('string'), $values)->willReturn('generated code');

        $fs->pathExists('/project/spec/Acme/AppSpec.php')->willReturn(false);
        $fs->isDirectory('/project/spec/Acme')->willReturn(true);
        $fs->putFileContents('/project/spec/Acme/AppSpec.php', 'template code')->shouldBeCalled();

        $this->generate($resource);
    }

    function it_creates_folder_for_spec_if_needed($io, TemplateRenderer $tpl, $fs, Resource $resource)
    {
        $tpl->render('specification', Argument::type('array'))->willReturn('rendered string');
        $resource->getSpecName()->willReturn('AppAppSpec');
        $resource->getSpecFilename()->willReturn('/project/spec/Acme/AppSpec.php');
        $resource->getSpecNamespace()->willReturn('spec\Acme');
        $resource->getSrcClassname()->willReturn('Acme\App');
        $resource->getName()->willReturn('App');

        $fs->pathExists('/project/spec/Acme/AppSpec.php')->willReturn(false);
        $fs->isDirectory('/project/spec/Acme')->willReturn(false);
        $fs->makeDirectory('/project/spec/Acme')->shouldBeCalled();
        $fs->putFileContents('/project/spec/Acme/AppSpec.php', Argument::any())->willReturn(null);

        $this->generate($resource);
    }

    function it_asks_confirmation_if_spec_already_exists(
        $io, $tpl, $fs, Resource $resource
    ) {
        $resource->getSpecName()->willReturn('AppSpec');
        $resource->getSpecFilename()->willReturn('/project/spec/Acme/AppSpec.php');
        $resource->getSpecNamespace()->willReturn('spec\Acme');
        $resource->getSrcClassname()->willReturn('Acme\App');

        $fs->pathExists('/project/spec/Acme/AppSpec.php')->willReturn(true);
        $io->askConfirmation(Argument::type('string'), false)->willReturn(false);

        $fs->putFileContents(Argument::cetera())->shouldNotBeCalled();

        $this->generate($resource);
    }
}
