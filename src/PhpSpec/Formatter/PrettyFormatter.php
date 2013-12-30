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

use PhpSpec\IO\IOInterface as IO;
use PhpSpec\Formatter\Presenter\PresenterInterface;
use PhpSpec\Listener\StatisticsCollector;

use PhpSpec\Event\SuiteEvent;
use PhpSpec\Event\SpecificationEvent;
use PhpSpec\Event\ExampleEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PrettyFormatter implements EventSubscriberInterface
{
    private $io;
    private $presenter;
    private $stats;

    public function __construct(PresenterInterface $presenter, IO $io, StatisticsCollector $stats)
    {
        $this->presenter = $presenter;
        $this->io = $io;
        $this->stats = $stats;
    }

    public static function getSubscribedEvents()
    {
        $events = array('beforeSpecification', 'afterExample', 'afterSuite');

        return array_combine($events, $events);
    }

    public function setIO(IO $io)
    {
        $this->io = $io;
    }

    public function setPresenter(PresenterInterface $presenter)
    {
        $this->presenter = $presenter;
    }

    public function beforeSpecification(SpecificationEvent $event)
    {
        $this->io->writeln(sprintf("\n      %s\n", $event->getSpecification()->getTitle()), 0);
    }

    public function afterExample(ExampleEvent $event)
    {
        $line  = $event->getExample()->getFunctionReflection()->getStartLine();
        $depth = 2;
        $title = preg_replace('/^it /', '', $event->getExample()->getTitle());

        $this->io->write(sprintf('<lineno>%4d</lineno> ', $line));

        switch ($event->getResult()) {
            case ExampleEvent::PASSED:
                $this->io->write(sprintf('<passed>✔ %s</passed>', $title), $depth - 1);
                break;
            case ExampleEvent::PENDING:
                $this->io->write(sprintf('<pending>- %s</pending>', $title), $depth - 1);
                break;
            case ExampleEvent::FAILED:
                $this->io->write(sprintf('<failed>✘ %s</failed>', $title), $depth - 1);
                break;
            case ExampleEvent::BROKEN:
                $this->io->write(sprintf('<broken>! %s</broken>', $title), $depth - 1);
                break;
        }

        $this->printSlowTime($event);
        $this->io->writeln();
        $this->printException($event);
    }

    public function afterSuite(SuiteEvent $event)
    {
        $this->io->writeln();

        foreach (array(
            'failed' => $this->stats->getFailedEvents(),
            'broken' => $this->stats->getBrokenEvents()
        ) as $status => $events) {
            if (!count($events)) {
                continue;
            }

            $this->io->writeln(sprintf("<%s>----  %s examples</%s>\n", $status, $status, $status));
            foreach ($events as $failEvent) {
                $this->io->writeln(sprintf('%s',
                    str_replace('\\', DIRECTORY_SEPARATOR, $failEvent->getSpecification()->getTitle())
                ), 8);
                $this->afterExample($failEvent);
                $this->io->writeln();
            }
        }

        $this->io->writeln(sprintf("\n%d specs", $this->stats->getTotalSpecs()));

        $counts = array();
        foreach ($this->stats->getCountsHash() as $type => $count) {
            if ($count) {
                $counts[] = sprintf('<%s>%d %s</%s>', $type, $count, $type, $type);
            }
        }

        $this->io->write(sprintf("%d examples ", $this->stats->getEventsCount()));
        if (count($counts)) {
            $this->io->write(sprintf("(%s)", implode(', ', $counts)));
        }

        $this->io->writeln(sprintf("\n%sms", round($event->getTime() * 1000)));
    }

    protected function printSlowTime(ExampleEvent $event)
    {
        $ms = $event->getTime() * 1000;
        if ($ms > 100) {
            $this->io->write(sprintf(' <failed>(%sms)</failed>', round($ms)));
        } elseif ($ms > 50) {
            $this->io->write(sprintf(' <pending>(%sms)</pending>', round($ms)));
        }
    }

    protected function printException(ExampleEvent $event, $depth = null)
    {
        if (null === $exception = $event->getException()) {
            return;
        }

        $depth = $depth ?: 8;
        $message = $this->presenter->presentException($exception, $this->io->isVerbose());

        if (ExampleEvent::FAILED === $event->getResult()) {
            $this->io->writeln(sprintf('<failed>%s</failed>', lcfirst($message)), $depth);
        } elseif (ExampleEvent::PENDING === $event->getResult()) {
            $this->io->writeln(sprintf('<pending>%s</pending>', lcfirst($message)), $depth);
        } else {
            $this->io->writeln(sprintf('<broken>%s</broken>', lcfirst($message)), $depth);
        }
    }
}
