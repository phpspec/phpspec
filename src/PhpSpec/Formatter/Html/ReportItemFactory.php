<?php

namespace PhpSpec\Formatter\Html;

use PhpSpec\Event\ExampleEvent;
use PhpSpec\Formatter\Html\IO;
use PhpSpec\Formatter\Presenter\PresenterInterface;

class ReportItemFactory
{

    public function create(IO $io, ExampleEvent $event, PresenterInterface $presenter = null)
    {
        switch(true) {
            case $event->getResult() === ExampleEvent::PASSED:
                return new ReportPassedItem($io, $event);
            case $event->getResult() === ExampleEvent::PENDING:
                return new ReportPendingItem($io, $event);
            case $event->getResult() === ExampleEvent::FAILED:
                return new ReportFailedItem($io, $event, $presenter);
            case $event->getResult() === ExampleEvent::BROKEN:
                return new ReportBrokenItem($io, $event, $presenter);
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