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
use PhpSpec\Message\CurrentExample;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CurrentExampleListener implements EventSubscriberInterface {

    /**
     * @var CurrentExample
     */
    private $message;

    public static function getSubscribedEvents()
    {
        return array(
            'beforeExample' => array('beforeExampleMessage', -20),
            'afterExample' => array('afterExampleMessage', -20),
            'afterSuite' => array('suiteMessage', -20),
        );
    }

    public function __construct(CurrentExample $message)
    {
        $this->message = $message;
    }

    public function beforeExampleMessage(ExampleEvent $event)
    {
        $this->message->setCurrentExample($event->getTitle());
    }

    public function afterExampleMessage()
    {
        $message = "";
        $this->message->setCurrentExample($message);
    }

    public function suiteMessage(SuiteEvent $event)
    {
        $this->message->setCurrentExample($event->getResult());
    }
}
