<?php

namespace PhpSpec\Formatter\Html;

use PhpSpec\Event\ExampleEvent;
use PhpSpec\Formatter\Html\IO;
use PhpSpec\Formatter\Template as TemplateInterface;

class ReportPassedItem
{
    private $io;
    private $event;

    public function __construct(TemplateInterface $template, IO $io, ExampleEvent $event)
    {
        $this->io = $io;
        $this->event = $event;
    }

    public function write()
    {
        $this->io->write(
            sprintf('          <dd class="example passed">%s</dd>', $this->event->getTitle())
        );
    }
}