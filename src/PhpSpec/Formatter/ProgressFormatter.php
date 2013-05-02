<?php

namespace PhpSpec\Formatter;

use PhpSpec\Console\IO;
use PhpSpec\Formatter\Presenter\PresenterInterface;
use PhpSpec\Listener\StatisticsCollector;

use PhpSpec\Event\SuiteEvent;
use PhpSpec\Event\SpecificationEvent;
use PhpSpec\Event\ExampleEvent;
use PhpSpec\Exception\Example\PendingException;

class ProgressFormatter implements FormatterInterface
{
    private $io;
    private $presenter;
    private $stats;

    public static function getSubscribedEvents()
    {
        $events = array('afterExample', 'afterSuite');

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

    public function setStatisticsCollector(StatisticsCollector $stats)
    {
        $this->stats = $stats;
    }

    public function afterExample(ExampleEvent $event)
    {
        $total  = $this->stats->getEventsCount();
        $counts = $this->stats->getCountsHash();

        $percents = array_map(function($count) use($total) {
            return round($count / ($total / 100), 0);
        }, $counts);
        $lengths  = array_map(function($percent) {
            return round($percent / 2, 0);
        }, $percents);

        $size = 50;
        asort($lengths);
        $progress = array();
        foreach ($lengths as $status => $length) {
            $text   = $percents[$status].'%';
            $length = ($size - $length) >= 0 ? $length : $size;
            $size   = $size - $length;

            if ($this->io->isDecorated()) {
                if ($length > strlen($text) + 2) {
                    $text = str_pad($text, $length, ' ', STR_PAD_BOTH);
                } else {
                    $text = str_pad('', $length, ' ');
                }

                $progress[$status] = sprintf("<$status-bg>%s</$status-bg>", $text);
            } else {
                $progress[$status] = str_pad(
                    sprintf('%s: %s', $status, $text), 15, ' ', STR_PAD_BOTH
                );
            }
        }
        krsort($progress);

        $this->printException($event, 2);
        if ($this->io->isDecorated()) {
            $this->io->writeTemp(implode('', $progress).' '.$total);
        } else {
            $this->io->writeTemp('/'.implode('/', $progress).'/  '.$total.' examples');
        }
    }

    public function afterSuite(SuiteEvent $event)
    {
        $this->io->freezeTemp();
        $this->io->writeln();

        $counts = array();
        foreach ($this->stats->getCountsHash() as $type => $count) {
            if ($count) {
                $counts[] = sprintf('<%s>%d %s</%s>', $type, $count, $type, $type);
            }
        }
        $count = $this->stats->getEventsCount();
        $plural = $count !== 1 ? 's' : '';
        $this->io->write(sprintf("\n%d example%s ", $count, $plural));
        if (count($counts)) {
            $this->io->write(sprintf("(%s)", implode(', ', $counts)));
        }

        $this->io->writeln(sprintf("\n%sms", round($event->getTime() * 1000)));
    }

    protected function printException(ExampleEvent $event)
    {
        if (null === $exception = $event->getException()) {
            return;
        }

        $title = str_replace('\\', DIRECTORY_SEPARATOR, $event->getSpecification()->getTitle());
        $title = str_pad($title, 50, ' ', STR_PAD_RIGHT);
        $message = $this->presenter->presentException($exception, $this->io->isVerbose());

        if ($exception instanceof PendingException) {
            $this->io->writeln(sprintf('<pending-bg>%s</pending-bg>', $title));
            $this->io->writeln(sprintf(
                '<lineno>%4d</lineno>  <pending>- %s</pending>',
                $event->getExample()->getFunctionReflection()->getStartLine(),
                $event->getExample()->getTitle()
            ));
            $this->io->writeln(sprintf('<pending>%s</pending>', lcfirst($message)), 6);
        } elseif (ExampleEvent::FAILED === $event->getResult()) {
            $this->io->writeln(sprintf('<failed-bg>%s</failed-bg>', $title));
            $this->io->writeln(sprintf(
                '<lineno>%4d</lineno>  <failed>✘ %s</failed>',
                $event->getExample()->getFunctionReflection()->getStartLine(),
                $event->getExample()->getTitle()
            ));
            $this->io->writeln(sprintf('<failed>%s</failed>', lcfirst($message)), 6);
        } else {
            $this->io->writeln(sprintf('<broken-bg>%s</broken-bg>', $title));
            $this->io->writeln(sprintf(
                '<lineno>%4d</lineno>  <broken>! %s</broken>',
                $event->getExample()->getFunctionReflection()->getStartLine(),
                $event->getExample()->getTitle()
            ));
            $this->io->writeln(sprintf('<broken>%s</broken>', lcfirst($message)), 6);
        }

        $this->io->writeln();
    }
}
