<?php

namespace spec\PhpSpec\CodeGenerator\Generator;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class InterfaceGeneratorSpec extends ObjectBehavior
{
    /**
     * @param PhpSpec\Console\IO $io
     * @param PhpSpec\CodeGenerator\TemplateRenderer $tpl
     * @param PhpSpec\Util\Filesystem $fs
     */
    function let($io, $tpl, $fs)
    {
        $this->beConstructedWith($io, $tpl, $fs);
    }

    function it_is_a_generator()
    {
        $this->shouldBeAnInstanceOf('PhpSpec\CodeGenerator\Generator\GeneratorInterface');
    }

    /**
     * @param PhpSpec\Locator\ResourceInterface $resource
     */
    function it_supports_interface_generations($resource)
    {
        $this->supports($resource, 'interface', array())->shouldReturn(true);
    }

    /**
     * @param PhpSpec\Locator\ResourceInterface $resource
     */
    function it_does_not_support_class_generation($resource)
    {
        $this->supports($resource, 'class', array())->shouldReturn(false);
    }

    function its_priority_is_0()
    {
        $this->getPriority()->shouldReturn(0);
    }

    /**
     * @param PhpSpec\Console\IO $io
     * @param PhpSpec\CodeGenerator\TemplateRenderer $tpl
     * @param PhpSpec\Util\Filesystem $fs
     * @param PhpSpec\Locator\ResourceInterface $resource
     */
    function it_generates_interface_from_resource_and_puts_it_into_appropriate_folder(
        $io, $tpl, $fs, $resource
    )
    {
        $resource->getName()->willReturn('AppInterface');
        $resource->getSrcFilename()->willReturn('/project/src/Acme/AppInterface.php');
        $resource->getSrcNamespace()->willReturn('Acme');
        $resource->getSrcClassname()->willReturn('Acme\App');

        $values = array(
            '%filepath%'        => '/project/src/Acme/AppInterface.php',
            '%name%'            => 'AppInterface',
            '%namespace%'       => 'Acme',
            '%namespace_block%' => "\n\nnamespace Acme;",
        );

        $tpl->render('interface', $values)->willReturn(null);
        $tpl->renderString(Argument::type('string'), $values)->willReturn('generated code');

        $fs->pathExists('/project/src/Acme/AppInterface.php')->willReturn(false);
        $fs->isDirectory('/project/src/Acme')->willReturn(true);
        $fs->putFileContents('/project/src/Acme/AppInterface.php', 'generated code')->shouldBeCalled();

        $this->generate($resource);
    }

    /**
     * @param PhpSpec\Console\IO $io
     * @param PhpSpec\CodeGenerator\TemplateRenderer $tpl
     * @param PhpSpec\Util\Filesystem $fs
     * @param PhpSpec\Locator\ResourceInterface $resource
     */
    function it_uses_template_provided_by_templating_system_if_there_is_one(
        $io, $tpl, $fs, $resource
    )
    {
        $resource->getName()->willReturn('AppInterface');
        $resource->getSrcFilename()->willReturn('/project/src/Acme/AppInterface.php');
        $resource->getSrcNamespace()->willReturn('Acme');
        $resource->getSrcClassname()->willReturn('Acme\App');

        $values = array(
            '%filepath%'        => '/project/src/Acme/AppInterface.php',
            '%name%'            => 'AppInterface',
            '%namespace%'       => 'Acme',
            '%namespace_block%' => "\n\nnamespace Acme;",
        );

        $tpl->render('interface', $values)->willReturn('template code');
        $tpl->renderString(Argument::type('string'), $values)->willReturn('generated code');

        $fs->pathExists('/project/src/Acme/AppInterface.php')->willReturn(false);
        $fs->isDirectory('/project/src/Acme')->willReturn(true);
        $fs->putFileContents('/project/src/Acme/AppInterface.php', 'template code')->shouldBeCalled();

        $this->generate($resource);
    }

    /**
     * @param PhpSpec\Console\IO $io
     * @param PhpSpec\Util\Filesystem $fs
     * @param PhpSpec\Locator\ResourceInterface $resource
     */
    function it_creates_folder_for_interface_if_needed($io, $fs, $resource)
    {
        $resource->getName()->willReturn('AppInterface');
        $resource->getSrcFilename()->willReturn('/project/src/Acme/AppInterface.php');
        $resource->getSrcNamespace()->willReturn('Acme');
        $resource->getSrcClassname()->willReturn('Acme\App');

        $fs->pathExists('/project/src/Acme/AppInterface.php')->willReturn(false);
        $fs->isDirectory('/project/src/Acme')->willReturn(false);
        $fs->makeDirectory('/project/src/Acme')->shouldBeCalled();
        $fs->putFileContents('/project/src/Acme/AppInterface.php', Argument::any())->willReturn(null);

        $this->generate($resource);
    }

    /**
     * @param PhpSpec\Console\IO $io
     * @param PhpSpec\Util\Filesystem $fs
     * @param PhpSpec\Locator\ResourceInterface $resource
     */
    function it_asks_confirmation_if_interface_already_exists($io, $fs, $resource)
    {
        $resource->getName()->willReturn('AppInterface');
        $resource->getSrcFilename()->willReturn('/project/src/Acme/AppInterface.php');
        $resource->getSrcNamespace()->willReturn('Acme');
        $resource->getSrcClassname()->willReturn('Acme\App');

        $fs->pathExists('/project/src/Acme/AppInterface.php')->willReturn(true);
        $io->askConfirmation(Argument::type('string'), false)->willReturn(false);

        $fs->putFileContents(Argument::cetera())->shouldNotBeCalled();

        $this->generate($resource);
    }
}
