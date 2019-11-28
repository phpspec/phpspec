<?php

namespace spec\PhpSpec\CodeGenerator\Generator;

use PhpSpec\CodeGenerator\Generator\Generator;
use PhpSpec\Event\BaseEvent;
use PhpSpec\Event\FileCreationEvent;
use PhpSpec\Locator\Resource;
use PhpSpec\ObjectBehavior;
use PhpSpec\Util\DispatchTrait;
use PhpSpec\Util\Filesystem;
use Prophecy\Argument;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class NewFileNotifyingGeneratorSpec extends ObjectBehavior
{
    use DispatchTrait;

    const EVENT_CLASS = 'PhpSpec\Event\FileCreationEvent';

    public function let(Generator $generator, EventDispatcherInterface $dispatcher, Filesystem $filesystem)
    {
        $dispatcher->dispatch(Argument::any(), Argument::any())->willReturnArgument(0);
        $this->beConstructedWith($generator, $dispatcher, $filesystem);
    }

    function it_is_a_code_generator()
    {
        $this->shouldImplement('PhpSpec\CodeGenerator\Generator\Generator');
    }

    function it_should_proxy_the_support_call_to_the_decorated_object($generator, Resource $resource)
    {
        $generator->supports($resource, 'foo', array('bar'))->willReturn(true);
        $this->supports($resource, 'foo', array('bar'))->shouldReturn(true);
    }

    function it_should_proxy_the_priority_call_to_the_decorated_object($generator)
    {
        $generator->getPriority()->willReturn(5);
        $this->getPriority()->shouldReturn(5);
    }

    function it_should_proxy_the_generate_call_to_the_decorated_object(Generator $generator, Resource $resource, Filesystem $filesystem)
    {
        $generator->supports(Argument::cetera())->willReturn(true);
        $resource->getSpecFilename()->willReturn('');
        $filesystem->pathExists(Argument::any())->willReturn(true);

        $generator->generate($resource, array())->shouldBeCalled();

        $this->generate($resource, array());
    }

    function it_should_dispatch_an_event_when_a_file_is_created(Generator $generator, $dispatcher, $filesystem, Resource $resource)
    {
        $generator->supports(Argument::cetera())->willReturn(false);
        $path = '/foo';
        $resource->getSrcFilename()->willReturn($path);
        $event = new FileCreationEvent($path);
        $filesystem->pathExists($path)->willReturn(false, true);
        $generator->generate($resource, array())->shouldBeCalled();

        $this->generate($resource, array());

        $this->dispatch($dispatcher, $event, 'afterFileCreation')->willReturn($event);
    }

    function it_should_dispatch_an_event_with_the_spec_path_when_a_spec_is_created($generator, $dispatcher, $filesystem, Resource $resource)
    {
        $path = '/foo';
        $generator->supports($resource, 'specification', array())->willReturn(true);
        $generator->generate(Argument::cetera())->shouldBeCalled();
        $resource->getSpecFilename()->willReturn($path);
        $filesystem->pathExists($path)->willReturn(false, true);
        $event = new FileCreationEvent($path);

        $this->generate($resource, array());

        $this->dispatch($dispatcher, $event, 'afterFileCreation')->shouldHaveBeenCalled();
    }

    function it_should_check_that_the_file_was_created($generator, $filesystem, Resource $resource)
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

    function it_should_not_dispatch_an_event_if_the_file_was_not_created(Generator $generator, $dispatcher, $filesystem, Resource $resource)
    {
        $generator->supports(Argument::cetera())->willReturn(false);
        $generator->generate($resource, array())->shouldBeCalled();
        $path = '/foo';
        $resource->getSrcFilename()->willReturn($path);
        $filesystem->pathExists($path)->willReturn(false);

        $this->generate($resource, array());

        $this->dispatch($dispatcher, Argument::any(), 'afterFileCreation')->shouldNotHaveBeenCalled();
    }

    function it_should_not_dispatch_an_event_if_the_file_already_existed(Generator $generator, $dispatcher, $filesystem, Resource $resource)
    {
        $generator->supports(Argument::cetera())->willReturn(false);
        $generator->generate($resource, array())->shouldBeCalled();
        $path = '/foo';
        $resource->getSrcFilename()->willReturn($path);

        $filesystem->pathExists($path)->willReturn(true);

        $this->generate($resource, array());

        $this->dispatch($dispatcher, Argument::any(), 'afterFileCreation')->shouldNotHaveBeenCalled();
    }
}
