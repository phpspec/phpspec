<?php

namespace PhpSpec\Formatter\Html;

use PhpSpec\Event\ExampleEvent;
use PhpSpec\Formatter\Html\IO;

class ReportPassedItem
{
    private $io;
    private $event;

    public function __construct(IO $io, ExampleEvent $event)
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