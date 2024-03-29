<?php

namespace spec\PhpSpec\Formatter;

use PhpSpec\Formatter\Html\ReportPassedItem;
use PhpSpec\IO\IO;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

use PhpSpec\Event\ExampleEvent;
use PhpSpec\Formatter\Html\ReportItemFactory;
use PhpSpec\Formatter\Presenter\Presenter;
use PhpSpec\Listener\StatisticsCollector;

class HtmlFormatterSpec extends ObjectBehavior
{
    const EVENT_TITLE = 'it works';

    function let(ReportItemFactory $factory, Presenter $presenter, IO $io, StatisticsCollector $stats)
    {
        $this->beConstructedWith($factory, $presenter, $io, $stats);
    }

    function it_is_an_event_subscriber()
    {
        $this->shouldHaveType('Symfony\Component\EventDispatcher\EventSubscriberInterface');
    }

    function it_delegates_the_reporting_to_the_event_type_line_reporter(
        ExampleEvent $event, ReportPassedItem $item, ReportItemFactory $factory,
        Presenter $presenter)
    {
        $factory->create($event, $presenter)->willReturn($item);
        $item->write(Argument::any())->shouldBeCalled();
        $this->afterExample($event);
    }
}
