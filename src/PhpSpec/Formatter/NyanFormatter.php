<?php

namespace PhpSpec\Formatter;

use PhpSpec\Console\IO;
use PhpSpec\Formatter\Presenter\PresenterInterface;
use PhpSpec\Listener\StatisticsCollector;

use PhpSpec\Event\SuiteEvent;
use PhpSpec\Event\SpecificationEvent;
use PhpSpec\Event\ExampleEvent;
use PhpSpec\Exception\Example\PendingException;

use NyanCat\Cat;
use NyanCat\Rainbow;
use NyanCat\Team;
use NyanCat\Scoreboard;

use Fab\Factory as FabFactory;

class NyanFormatter extends DotFormatter
{
    private $examplesCount = 0;
    private $scoreboard;

    public function beforeSuite(SuiteEvent $event)
    {
        $io = $this->getIO();
        if (!$io->isDecorated()) {
            return parent::beforeSuite($event);
        }

        $this->examplesCount = count($event->getSuite());
        $length = strlen((string)$this->examplesCount) + 1;

        $this->scoreboard = new Scoreboard(
            new Cat(),
            new Rainbow(
                FabFactory::getFab(
                    empty($_SERVER['TERM']) ? 'unknown' : $_SERVER['TERM']
                ),
                array('-', '_'),
                39 - $length
            ),
            array(
                new Team('pass', 'green', '^'),
                new Team('pending', 'yellow', '-'),
                new Team('fail', 'red', 'o'),
                new Team('broken', 'magenta', 'o'),
            ),
            5,
            array($this->getIO(), 'write')
        );
    }

    public function afterExample(ExampleEvent $event)
    {
        $io = $this->getIO();
        if (!$io->isDecorated()) {
            return parent::afterExample($event);
        }

        $eventsCount = $this->getStatisticsCollector()->getEventsCount();
        if ($eventsCount === 1) {
            $io->writeln();

            if ($io->isDecorated()) {
                $this->scoreboard->start();
            }
        }

        switch ($event->getResult()) {
            case ExampleEvent::PASSED:
                $this->scoreboard->score('pass');
                break;
            case ExampleEvent::PENDING:
                $this->scoreboard->score('pending');
                break;
            case ExampleEvent::FAILED:
                $this->scoreboard->score('fail');
                break;
            case ExampleEvent::BROKEN:
                $this->scoreboard->score('broken');
                break;
        }
    }

    public function afterSuite(SuiteEvent $event)
    {
        $io = $this->getIO();
        if (!$io->isDecorated()) {
            return parent::afterSuite($event);
        }

        $this->scoreboard->stop();
        $io->writeln();

        $stats = $this->getStatisticsCollector();

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
