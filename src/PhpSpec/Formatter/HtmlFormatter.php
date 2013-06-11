<?php

namespace PhpSpec\Formatter;

use PhpSpec\Event\ExampleEvent;
use PhpSpec\Formatter\Presenter\PresenterInterface;

class HtmlFormatter extends BasicFormatter
{
    private $reportItemFactory;
    private $presenter;

    public function __construct(Html\ReportItemFactory $reportItemFactory = null)
    {
        $this->reportItemFactory = $reportItemFactory ?: new Html\ReportItemFactory();
    }

    public function setPresenter(PresenterInterface $presenter)
    {
        $this->presenter = $presenter;
    }

    public function afterExample(ExampleEvent $event)
    {
        $reportLine = $this->reportItemFactory->create($this->getIO(), $event, $this->presenter);
        $reportLine->write();
    }
}
