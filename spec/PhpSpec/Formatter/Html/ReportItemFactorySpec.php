<?php

namespace spec\PhpSpec\Formatter\Html;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

use PhpSpec\Event\ExampleEvent;
use PhpSpec\Console\IO;

class ReportItemFactorySpec extends ObjectBehavior
{
    function it_creates_a_ReportPassedItem(IO $io, ExampleEvent $event)
    {
        $event->getResult()->willReturn(ExampleEvent::PASSED);
        $this->create($io, $event)->shouldHaveType('PhpSpec\Formatter\Html\ReportPassedItem');
    }

    function it_creates_a_ReportPendingItem(IO $io, ExampleEvent $event)
    {
        $event->getResult()->willReturn(ExampleEvent::PENDING);
        $this->create($io, $event)->shouldHaveType('PhpSpec\Formatter\Html\ReportPendingItem');
    }

    function it_creates_a_ReportFailedItem(IO $io, ExampleEvent $event)
    {
        $event->getResult()->willReturn(ExampleEvent::FAILED);
        $this->create($io, $event)->shouldHaveType('PhpSpec\Formatter\Html\ReportFailedItem');
    }

    function it_creates_a_ReportBrokenItem(IO $io, ExampleEvent $event)
    {
        $event->getResult()->willReturn(ExampleEvent::BROKEN);
        $this->create($io, $event)->shouldHaveType('PhpSpec\Formatter\Html\ReportBrokenItem');
    }

}
