<?php

namespace PhpSpec\Formatter\Html;

use PhpSpec\Event\ExampleEvent;
use PhpSpec\Console\IO;

class ReportItemFactory
{

    public function create(IO $io, ExampleEvent $event)
    {
        switch(true) {
            case $event->getResult() === ExampleEvent::PASSED:
                return new ReportPassedItem($io, $event);
            case $event->getResult() === ExampleEvent::PENDING:
                return new ReportPendingItem($io, $event);
            case $event->getResult() === ExampleEvent::FAILED:
                return new ReportFailedItem($io, $event);
            case $event->getResult() === ExampleEvent::BROKEN:
                return new ReportBrokenItem($io, $event);
            default:
                throw $this->invalidResultException($event->getResult());
        }
    }

    private function invalidResultException($result)
    {
        throw new InvalidExampleResultException(
            "Unrecognised example result $result"
        );
    }
}