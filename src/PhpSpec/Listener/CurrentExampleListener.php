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

namespace PhpSpec\Listener;

use PhpSpec\Event\ExampleEvent;
use PhpSpec\Event\SuiteEvent;
use PhpSpec\Message\CurrentExampleTracker;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class CurrentExampleListener implements EventSubscriberInterface, ExampleListener, SuiteListener
{
    /**
     * @var CurrentExampleTracker
     */
    private $currentExample;

    public static function getSubscribedEvents()
    {
        return array(
            'beforeExample' => array('beforeExample', -20),
            'afterExample' => array('afterExample', -20),
            'afterSuite' => array('afterSuite', -20),
        );
    }

    public function __construct(CurrentExampleTracker $currentExample)
    {
        $this->currentExample = $currentExample;
    }

    public function beforeExample(ExampleEvent $event)
    {
        $this->currentExample->setCurrentExample($event->getTitle());
    }

    public function afterExample(ExampleEvent $event)
    {
        $this->currentExample->setCurrentExample(null);
    }

    public function beforeSuite(SuiteEvent $suiteEvent)
    {
    }

    public function afterSuite(SuiteEvent $suiteEvent)
    {
        $this->currentExample->setCurrentExample('Exited with code: ' . $suiteEvent->getResult());
    }
}
