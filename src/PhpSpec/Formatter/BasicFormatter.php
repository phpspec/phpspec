<?php

namespace PhpSpec\Formatter;

use PhpSpec\IO\IOInterface as IO;
use PhpSpec\Formatter\Presenter\PresenterInterface;
use PhpSpec\Listener\StatisticsCollector;

use PhpSpec\Event\SuiteEvent;
use PhpSpec\Event\SpecificationEvent;
use PhpSpec\Event\ExampleEvent;
use PhpSpec\Exception\Example\PendingException;

abstract class BasicFormatter implements FormatterInterface
{
    private $io;
    private $presenter;
    private $stats;

    public static function getSubscribedEvents()
    {
        $events = array(
            'beforeSuite', 'afterSuite',
            'beforeExample', 'afterExample',
            'beforeSpecification', 'afterSpecification'
        );

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

    protected function getIO()
    {
        return $this->io;
    }

    protected function getPresenter()
    {
        return $this->presenter;
    }

    protected function getStatisticsCollector()
    {
        return $this->stats;
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

    public function beforeSuite(SuiteEvent $event)
    {
    }

    public function afterSuite(SuiteEvent $event)
    {
    }

    public function beforeExample(ExampleEvent $event)
    {
    }

    public function afterExample(ExampleEvent $event)
    {
    }

    public function beforeSpecification(SpecificationEvent $event)
    {
    }

    public function afterSpecification(SpecificationEvent $event)
    {
    }
}
