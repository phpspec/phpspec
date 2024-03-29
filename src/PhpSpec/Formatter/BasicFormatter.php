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

use PhpSpec\Formatter\Presenter\Presenter;
use PhpSpec\IO\IO;
use PhpSpec\Listener\StatisticsCollector;
use PhpSpec\Event\SuiteEvent;
use PhpSpec\Event\SpecificationEvent;
use PhpSpec\Event\ExampleEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

abstract class BasicFormatter implements EventSubscriberInterface
{
    public function __construct(
        private Presenter $presenter,
        private IO $io,
        private StatisticsCollector $stats
    )
    {
    }

    public static function getSubscribedEvents(): array
    {
        $events = array(
            'beforeSuite', 'afterSuite',
            'beforeExample', 'afterExample',
            'beforeSpecification', 'afterSpecification'
        );

        return array_combine($events, $events);
    }

    protected function getIO(): IO
    {
        return $this->io;
    }

    protected function getPresenter(): Presenter
    {
        return $this->presenter;
    }

    protected function getStatisticsCollector(): StatisticsCollector
    {
        return $this->stats;
    }

    public function beforeSuite(SuiteEvent $event) : void
    {
    }

    public function afterSuite(SuiteEvent $event) : void
    {
    }

    public function beforeExample(ExampleEvent $event) : void
    {
    }

    public function afterExample(ExampleEvent $event) : void
    {
    }

    public function beforeSpecification(SpecificationEvent $event) : void
    {
    }

    public function afterSpecification(SpecificationEvent $event) : void
    {
    }
}
