<?php

namespace PhpSpec\Formatter;

use PhpSpec\Event\ExampleEvent;
use PhpSpec\Event\SuiteEvent;
use PhpSpec\Formatter\Presenter\PresenterInterface;

class HtmlFormatter extends BasicFormatter
{
    private $reportItemFactory;

    public function __construct(Html\ReportItemFactory $reportItemFactory = null)
    {
        $this->reportItemFactory = $reportItemFactory ?: new Html\ReportItemFactory();
    }

    public function beforeSuite(SuiteEvent $suite)
    {
        include __DIR__ . "/Html/Template/ReportHeader.html";
    }

    public function afterExample(ExampleEvent $event)
    {
        $reportLine = $this->reportItemFactory->create($this->getIO(), $event, $this->getPresenter());
        $reportLine->write();
        $this->getIO()->write(PHP_EOL);
    }

    public function afterSuite(SuiteEvent $suite)
    {
        include __DIR__ . "/Html/Template/ReportSummary.html";
        include __DIR__ . "/Html/Template/ReportFooter.html";
    }
}
