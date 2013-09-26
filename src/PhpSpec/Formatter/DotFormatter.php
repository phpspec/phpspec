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
    private $examplesCount = 0;

    public function beforeSuite(SuiteEvent $event)
    {
        $this->examplesCount = count($event->getSuite());
    }

    public function afterExample(ExampleEvent $event)
    {
        $io = $this->getIO();

        $eventsCount = $this->getStatisticsCollector()->getEventsCount();
        if($eventsCount === 1) {
            $io->writeln();
        }

        switch ($event->getResult()) {
            case ExampleEvent::PASSED:
                $io->write('<passed>.</passed>');
                break;
            case ExampleEvent::PENDING:
                $io->write('<pending>P</pending>');
                break;
            case ExampleEvent::FAILED:
                $io->write('<failed>F</failed>');
                break;
            case ExampleEvent::BROKEN:
                $io->write('<broken>B</broken>');
                break;
        }

        if($eventsCount % 50 === 0) {
            $length = strlen((string)$this->examplesCount);
            $format = sprintf(' %%%dd / %%%dd', $length, $length);
            $io->write(sprintf($format, $eventsCount, $this->examplesCount));

            if($eventsCount !== $this->examplesCount) {
                $io->writeLn();
            }
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

        $plural = $stats->getTotalSpecs() !== 1 ? 's' : '';
        $io->writeln(sprintf("%d spec%s", $stats->getTotalSpecs(), $plural));

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
