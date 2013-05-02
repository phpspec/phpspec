<?php

namespace PhpSpec\Formatter;

use PhpSpec\Console\IO;
use PhpSpec\Formatter\Presenter\PresenterInterface;
use PhpSpec\Listener\StatisticsCollector;

use PhpSpec\Event\SuiteEvent;
use PhpSpec\Event\SpecificationEvent;
use PhpSpec\Event\ExampleEvent;
use PhpSpec\Exception\Example\PendingException;

class DotFormatter extends BasicFormatter
{
    public function afterExample(ExampleEvent $event)
    {
        if($this->getStatisticsCollector()->getEventsCount() % 50 === 1) {
            $this->getIO()->writeln();
        }

        switch ($event->getResult()) {
            case ExampleEvent::PASSED:
                $this->getIO()->write('<passed>.</passed>');
                break;
            case ExampleEvent::PENDING:
                $this->getIO()->write('<pending>P</pending>');
                break;
            case ExampleEvent::FAILED:
                $this->getIO()->write('<failed>F</failed>');
                break;
            case ExampleEvent::BROKEN:
                $this->getIO()->write('<broken>B</broken>');
                break;
        }
    }

    public function afterSuite(SuiteEvent $event)
    {
        $io = $this->getIO();
        $stats = $this->getStatisticsCollector();

        $io->writeln("\n");

        foreach (array(
            'failed' => $stats->getFailedEvents(),
            'broken' => $stats->getBrokenEvents(),
            'pending' => $stats->getPendingEvents()
        ) as $status => $events) {
            if (!count($events)) {
                continue;
            }

            foreach ($events as $failEvent) {
                $this->printException($failEvent);
            }
        }

        $counts = array();
        foreach ($stats->getCountsHash() as $type => $count) {
            if ($count) {
                $counts[] = sprintf('<%s>%d %s</%s>', $type, $count, $type, $type);
            }
        }

        $count = $stats->getEventsCount();
        $plural = $count !== 1 ? 's' : '';
        $io->write(sprintf("%d example%s ", $count, $plural));
        if (count($counts)) {
            $io->write(sprintf("(%s)", implode(', ', $counts)));
        }

        $io->writeln(sprintf("\n%sms", round($event->getTime() * 1000)));
    }
}
