<?php

namespace spec\PhpSpec\Formatter;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

use PhpSpec\Event\ExampleEvent;
use PhpSpec\Formatter\Html\IO;
use PhpSpec\Formatter\Html\ReportItem;
use PhpSpec\Formatter\Html\ReportItemFactory;
use PhpSpec\Formatter\Presenter\PresenterInterface as Presenter;

class HtmlFormatterSpec extends ObjectBehavior
{
    const EVENT_TITLE = 'it works';
    
    function let(ReportItemFactory $factory)
    {
        $this->beConstructedWith($factory);
    }
    
    function it_delegates_the_reporting_to_the_event_type_line_reporter(
        IO $io,
        ExampleEvent $event,
        ReportItem $item,
        ReportItemFactory $factory,
        Presenter $presenter
    )
    {
        $this->setPresenter($presenter);
        $this->setIo($io);
        $factory->create($event, $presenter)->willReturn($item);
        $item->write(Argument::any())->shouldBeCalled();
        $this->afterExample($event);
    }
}
