<?php

namespace PhpSpec\Formatter\Html;

use PhpSpec\Event\ExampleEvent;
use PhpSpec\Formatter\Presenter\PresenterInterface;
use PhpSpec\Formatter\Template as TemplateInterface;

class ReportItemFactory
{
    private $template;

    public function __construct(TemplateInterface $template)
    {
        $this->template = $template ?: new Template;
    }

    public function create(ExampleEvent $event, PresenterInterface $presenter = null)
    {
        switch(true) {
            case $event->getResult() === ExampleEvent::PASSED:
                return new ReportPassedItem($this->template, $event);
            case $event->getResult() === ExampleEvent::PENDING:
                return new ReportPendingItem($this->template, $event);
            case $event->getResult() === ExampleEvent::FAILED:
            case $event->getResult() === ExampleEvent::BROKEN:
                return new ReportFailedItem($this->template, $event, $presenter);
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