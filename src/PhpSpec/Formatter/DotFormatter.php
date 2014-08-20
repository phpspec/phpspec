<?php

/*
 * This file is part of PhpSpec, A php toolset to drive emergent
 * design by specification.
 *
 * (c) Marcello Duarte <marcello.duarte@gmail.com>
 * (c) Konstantin Kudryashov <ever.zet@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpSpec\Formatter;

use PhpSpec\Event\SuiteEvent;
use PhpSpec\Event\ExampleEvent;

/**
 * Class DotFormatter
 * @package PhpSpec\Formatter
 */
class DotFormatter extends ConsoleFormatter
{
    /**
     * @var int
     */
    private $examplesCount = 0;

    /**
     * @param SuiteEvent $event
     */
    public function beforeSuite(SuiteEvent $event)
    {
        $this->examplesCount = count($event->getSuite());
    }

    /**
     * @param ExampleEvent $event
     */
    public function afterExample(ExampleEvent $event)
    {
        $eventsCount = $this->getStatisticsCollector()->getEventsCount();
        if ($eventsCount === 1) {
            $this->io->writeln();
        }

        switch ($event->getResult()) {
            case ExampleEvent::PASSED:
                $this->io->write('<passed>.</passed>');
                break;
            case ExampleEvent::PENDING:
                $this->io->write('<pending>P</pending>');
                break;
            case ExampleEvent::SKIPPED:
                $this->io->write('<skipped>S</skipped>');
                break;
            case ExampleEvent::FAILED:
                $this->io->write('<failed>F</failed>');
                break;
            case ExampleEvent::BROKEN:
                $this->io->write('<broken>B</broken>');
                break;
        }

        if ($eventsCount % 50 === 0) {
            $length = strlen((string) $this->examplesCount);
            $format = sprintf(' %%%dd / %%%dd', $length, $length);
            $this->io->write(sprintf($format, $eventsCount, $this->examplesCount));

            if ($eventsCount !== $this->examplesCount) {
                $this->io->writeLn();
            }
        }
    }

    /**
     * @param SuiteEvent $event
     */
    public function afterSuite(SuiteEvent $event)
    {
        $stats = $this->getStatisticsCollector();

        $this->io->writeln("\n");

        foreach (array(
            'failed' => $stats->getFailedEvents(),
            'broken' => $stats->getBrokenEvents(),
            'pending' => $stats->getPendingEvents(),
            'skipped' => $stats->getSkippedEvents(),
        ) as $status => $events) {
            if (!count($events)) {
                continue;
            }

            foreach ($events as $failEvent) {
                $this->printException($failEvent);
            }
        }

        $plural = $stats->getTotalSpecs() !== 1 ? 's' : '';
        $this->io->writeln(sprintf("%d spec%s", $stats->getTotalSpecs(), $plural));

        $counts = array();
        foreach ($stats->getCountsHash() as $type => $count) {
            if ($count) {
                $counts[] = sprintf('<%s>%d %s</%s>', $type, $count, $type, $type);
            }
        }

        $count = $stats->getEventsCount();
        $plural = $count !== 1 ? 's' : '';
        $this->io->write(sprintf("%d example%s ", $count, $plural));
        if (count($counts)) {
            $this->io->write(sprintf("(%s)", implode(', ', $counts)));
        }

        $this->io->writeln(sprintf("\n%sms", round($event->getTime() * 1000)));
    }
}
