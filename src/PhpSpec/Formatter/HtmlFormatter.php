<?php

namespace PhpSpec\Formatter;

use PhpSpec\Event\ExampleEvent;

class HtmlFormatter extends BasicFormatter
{
    private $reportItemFactory;

    public function __construct(Html\ReportItemFactory $reportItemFactory = null)
    {
        $this->reportItemFactory = $reportItemFactory ?: new Html\ReportItemFactory();
    }

    public function afterExample(ExampleEvent $event)
    {
        $reportLine = $this->reportItemFactory->create($this->getIO(), $event);
        $reportLine->write();
    }
}
