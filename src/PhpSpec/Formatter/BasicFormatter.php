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
use PhpSpec\Exception\Example\PendingException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use PhpSpec\Exception\Example\SkippingException;

/**
 * Class BasicFormatter
 * @package PhpSpec\Formatter
 */
abstract class BasicFormatter implements EventSubscriberInterface
{
    /**
     * @var IO
     */
    private $io;

    /**
     * @var PresenterInterface
     */
    private $presenter;

    /**
     * @var StatisticsCollector
     */
    private $stats;

    public function __construct(PresenterInterface $presenter, IO $io, StatisticsCollector $stats)
    {
        $this->presenter = $presenter;
        $this->io = $io;
        $this->stats = $stats;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        $events = array(
            'beforeSuite', 'afterSuite',
            'beforeExample', 'afterExample',
            'beforeSpecification', 'afterSpecification'
        );

        return array_combine($events, $events);
    }

    /**
     * @return mixed
     */
    protected function getIO()
    {
        return $this->io;
    }

    /**
     * @return mixed
     */
    protected function getPresenter()
    {
        return $this->presenter;
    }

    /**
     * @return mixed
     */
    protected function getStatisticsCollector()
    {
        return $this->stats;
    }

    /**
     * @param ExampleEvent $event
     */
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
            $this->io->writeln();
        } elseif ($exception instanceof SkippingException) {
            if ($this->io->isVerbose()) {
                $this->io->writeln(sprintf('<skipped-bg>%s</skipped-bg>', $title));
                $this->io->writeln(sprintf(
                    '<lineno>%4d</lineno>  <skipped>? %s</skipped>',
                    $event->getExample()->getFunctionReflection()->getStartLine(),
                    $event->getExample()->getTitle()
                ));
                $this->io->writeln(sprintf('<skipped>%s</skipped>', lcfirst($message)), 6);
                $this->io->writeln();
            }
        } elseif (ExampleEvent::FAILED === $event->getResult()) {
            $this->io->writeln(sprintf('<failed-bg>%s</failed-bg>', $title));
            $this->io->writeln(sprintf(
                '<lineno>%4d</lineno>  <failed>✘ %s</failed>',
                $event->getExample()->getFunctionReflection()->getStartLine(),
                $event->getExample()->getTitle()
            ));
            $this->io->writeln(sprintf('<failed>%s</failed>', lcfirst($message)), 6);
            $this->io->writeln();
        } else {
            $this->io->writeln(sprintf('<broken-bg>%s</broken-bg>', $title));
            $this->io->writeln(sprintf(
                '<lineno>%4d</lineno>  <broken>! %s</broken>',
                $event->getExample()->getFunctionReflection()->getStartLine(),
                $event->getExample()->getTitle()
            ));
            $this->io->writeln(sprintf('<broken>%s</broken>', lcfirst($message)), 6);
            $this->io->writeln();
        }
    }

    /**
     * @param SuiteEvent $event
     */
    public function beforeSuite(SuiteEvent $event)
    {
    }

    /**
     * @param SuiteEvent $event
     */
    public function afterSuite(SuiteEvent $event)
    {
    }

    /**
     * @param ExampleEvent $event
     */
    public function beforeExample(ExampleEvent $event)
    {
    }

    /**
     * @param ExampleEvent $event
     */
    public function afterExample(ExampleEvent $event)
    {
    }

    /**
     * @param SpecificationEvent $event
     */
    public function beforeSpecification(SpecificationEvent $event)
    {
    }

    /**
     * @param SpecificationEvent $event
     */
    public function afterSpecification(SpecificationEvent $event)
    {
    }
}
