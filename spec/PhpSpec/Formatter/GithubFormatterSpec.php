<?php

namespace spec\PhpSpec\Formatter;

use PhpSpec\Event\ExampleEvent;
use PhpSpec\Event\SuiteEvent;
use PhpSpec\IO\IO;
use PhpSpec\Loader\Node\ExampleNode;
use PhpSpec\Loader\Node\SpecificationNode;
use PhpSpec\Locator\Resource;
use PhpSpec\ObjectBehavior;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class GithubFormatterSpec extends ObjectBehavior
{
    function let(
        ExampleEvent $event,
        IO $io,
        ExampleNode $example,
        SpecificationNode $specification,
        Resource $resource
    )
    {
        $this->beConstructedWith($io, '/home/someuser/phpspec');

        $event->getExample()->willReturn($example);
        $example->getLineNumber()->willReturn(100);

        $event->getSpecification()->willReturn($specification);
        $specification->getResource()->willReturn($resource);
        $resource->getSpecFilename()->willReturn('/home/someuser/phpspec/MySpec.php');
    }

    function it_is_an_event_subscriber()
    {
        $this->shouldHaveType(EventSubscriberInterface::class);
    }

    function it_subscribes_to_events()
    {
        $this->getSubscribedEvents()->shouldReturn([
            'afterExample' => 'logError',
            'afterSuite' => 'printErrors'
        ]);
    }

    function it_outputs_failures_after_suite(
        ExampleEvent $event,
        IO $io,
        SuiteEvent $suiteEvent
    )
    {
        $event->getResult()->willReturn(ExampleEvent::FAILED);
        $event->getMessage()->willReturn("Oops it failed");

        $this->logError($event);
        $this->printErrors($suiteEvent);

        $io->write("\n")->shouldHaveBeenCalled();
        $io->write("::error file=MySpec.php,line=100,col=1::Failed: Oops it failed\n")->shouldHaveBeenCalled();
    }

    function it_outputs_broken_examples_after_suite(
        ExampleEvent $event,
        IO $io,
        SuiteEvent $suiteEvent
    )
    {
        $event->getResult()->willReturn(ExampleEvent::BROKEN);
        $event->getMessage()->willReturn("Oops it broke");

        $this->logError($event);
        $this->printErrors($suiteEvent);

        $io->write("\n")->shouldHaveBeenCalled();
        $io->write("::error file=MySpec.php,line=100,col=1::Broken: Oops it broke\n")->shouldHaveBeenCalled();
    }

    function it_outputs_error_with_escaping(
        ExampleEvent $event,
        IO $io,
        SuiteEvent $suiteEvent
    )
    {
        $event->getResult()->willReturn(ExampleEvent::FAILED);
        $event->getMessage()->willReturn("Oops:\r\nIt 100% broke");

        $this->logError($event);
        $this->printErrors($suiteEvent);

        $io->write("\n")->shouldHaveBeenCalled();
        $io->write("::error file=MySpec.php,line=100,col=1::Failed: Oops:%0D%0AIt 100%25 broke\n")->shouldHaveBeenCalled();

    }
}

