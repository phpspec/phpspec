<?php

namespace PhpSpec\Formatter;

use PhpSpec\Event\ExampleEvent;
use PhpSpec\Event\SuiteEvent;
use PhpSpec\Event\SpecificationEvent;
use PhpSpec\Formatter\Presenter\PresenterInterface;

class HtmlFormatter extends BasicFormatter
{
    private $reportItemFactory;
    private $index = 1;

    public function __construct(Html\ReportItemFactory $reportItemFactory = null)
    {
        $this->reportItemFactory = $reportItemFactory ?: new Html\ReportItemFactory();
    }

    public function beforeSuite(SuiteEvent $suite)
    {
        include __DIR__ . "/Html/Template/ReportHeader.html";
    }

    public function beforeSpecification(SpecificationEvent $specification)
    {
        $index = $this->index++;
        $name = $specification->getTitle();
        include __DIR__ . "/Html/Template/ReportSpecificationStarts.html";
    }

    public function afterSpecification(SpecificationEvent $specification)
    {
        include __DIR__ . "/Html/Template/ReportSpecificationEnds.html";
    }

    public function afterExample(ExampleEvent $event)
    {
        $reportLine = $this->reportItemFactory->create($event, $this->getPresenter());
        $reportLine->write($this->index - 1);
        $this->getIO()->write(PHP_EOL);
    }

    public function afterSuite(SuiteEvent $suite)
    {
        include __DIR__ . "/Html/Template/ReportSummary.html";
        include __DIR__ . "/Html/Template/ReportFooter.html";
    }
}
