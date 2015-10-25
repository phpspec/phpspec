<?php

namespace spec\PhpSpec\CodeGenerator\Generator;

use PhpSpec\CodeGenerator\Generator\GeneratorInterface;
use PhpSpec\Event\FileCreationEvent;
use PhpSpec\Locator\ResourceInterface;
use PhpSpec\ObjectBehavior;
use PhpSpec\Util\Filesystem;
use Prophecy\Argument;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class NewFileNotifyingGeneratorSpec extends ObjectBehavior
{
    const EVENT_CLASS = 'PhpSpec\Event\FileCreationEvent';

    public function let(GeneratorInterface $generator, EventDispatcherInterface $dispatcher, Filesystem $filesystem)
    {
        $this->beConstructedWith($generator, $dispatcher, $filesystem);
    }

    function it_is_a_code_generator()
    {
        $this->shouldImplement('PhpSpec\CodeGenerator\Generator\GeneratorInterface');
    }

    function it_should_proxy_the_support_call_to_the_decorated_object($generator, ResourceInterface $resource)
    {
        $generator->supports($resource, 'foo', array('bar'))->willReturn(true);
        $this->supports($resource, 'foo', array('bar'))->shouldReturn(true);
    }

    function it_should_proxy_the_priority_call_to_the_decorated_object($generator)
    {
        $generator->getPriority()->willReturn(5);
        $this->getPriority()->shouldReturn(5);
    }

    function it_should_proxy_the_generate_call_to_the_decorated_object($generator, ResourceInterface $resource)
    {
        $this->generate($resource, array());
        $generator->generate($resource, array())->shouldHaveBeenCalled();
    }

    function it_should_dispatch_an_event_when_a_file_is_created($dispatcher, $filesystem, ResourceInterface $resource)
    {
        $path = '/foo';
        $resource->getSrcFilename()->willReturn($path);
        $event = new FileCreationEvent($path);
        $filesystem->pathExists($path)->willReturn(false, true);

        $this->generate($resource, array());

        $dispatcher->dispatch('afterFileCreation', $event)->shouldHaveBeenCalled();
    }

    function it_should_dispatch_an_event_with_the_spec_path_when_a_spec_is_created($generator, $dispatcher, $filesystem, ResourceInterface $resource)
    {
        $path = '/foo';
        $generator->supports($resource, 'specification', array())->willReturn(true);
        $generator->generate(Argument::cetera())->shouldBeCalled();
        $resource->getSpecFilename()->willReturn($path);
        $filesystem->pathExists($path)->willReturn(false, true);
        $event = new FileCreationEvent($path);

        $this->generate($resource, array());

        $dispatcher->dispatch('afterFileCreation', $event)->shouldHaveBeenCalled();
    }

    function it_should_check_that_the_file_was_created($generator, $filesystem, ResourceInterface $resource)
    {
        $path = '/foo';
        $resource->getSrcFilename()->willReturn($path);

        $filesystem->pathExists($path)->willReturn(false);

        $generator->supports(Argument::cetera())->willReturn(false);
        $generator->generate($resource, array())->will(function () use ($filesystem, $path) {
            $filesystem->pathExists($path)->willReturn(true);
        });

        $this->generate($resource, array());
    }

    function it_should_not_dispatch_an_event_if_the_file_was_not_created($dispatcher, $filesystem, ResourceInterface $resource)
    {
        $path = '/foo';
        $resource->getSrcFilename()->willReturn($path);

        $filesystem->pathExists($path)->willReturn(false);

        $this->generate($resource, array());

        $dispatcher->dispatch('afterFileCreation', Argument::any())->shouldNotHaveBeenCalled();
    }

    function it_should_not_dispatch_an_event_if_the_file_already_existed($dispatcher, $filesystem, ResourceInterface $resource)
    {
        $path = '/foo';
        $resource->getSrcFilename()->willReturn($path);

        $filesystem->pathExists($path)->willReturn(true);

        $this->generate($resource, array());

        $dispatcher->dispatch('afterFileCreation', Argument::any())->shouldNotHaveBeenCalled();
    }
}
