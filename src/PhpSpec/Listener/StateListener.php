<?php

namespace PhpSpec\Listener;

use PhpSpec\Event\ExampleEvent;
use PhpSpec\Event\SuiteEvent;
use PhpSpec\Message\Example;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class StateListener implements EventSubscriberInterface
{

    /**
     * @var Example
     */
    private $message;

    public static function getSubscribedEvents() {
        return array(
            'beforeExample' => array('beforeExample', -20),
            'afterExample'  => array('afterExample', -20),
            'afterSuite'    => array('afterSuite', -20),
        );
    }

    public function __construct(Example $message)
    {
        $this->message = $message;
    }

    public function beforeExample(ExampleEvent $event)
    {
        $this->message->setExampleMessage($event->getTitle());
    }

    public function afterExample(ExampleEvent $event)
    {
        $this->message->setExampleMessage($event->getTitle());
    }

    public function afterSuite(SuiteEvent $event)
    {
        $this->message->setExampleMessage($event->getResult());
    }
}
