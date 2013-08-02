<?php

namespace PhpSpec\Formatter\Html;

use PhpSpec\Event\ExampleEvent;
use PhpSpec\Formatter\Html\IO;
use PhpSpec\Formatter\Presenter\PresenterInterface;
use PhpSpec\Formatter\Template as TemplateInterface;

class ReportItemFactory
{
    private $template;

    public function __construct(TemplateInterface $template)
    {
        $this->template = $template ?: new Template;
    }

    public function create(IO $io, ExampleEvent $event, PresenterInterface $presenter = null)
    {
        switch(true) {
            case $event->getResult() === ExampleEvent::PASSED:
                return new ReportPassedItem($this->template, $io, $event);
            case $event->getResult() === ExampleEvent::PENDING:
                return new ReportPendingItem($this->template, $io, $event);
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