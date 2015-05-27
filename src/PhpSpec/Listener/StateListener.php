<?php

namespace PhpSpec\Listener;

use PhpSpec\Event\ExampleEvent;
use PhpSpec\Event\SuiteEvent;
use PhpSpec\Message\MessageInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class StateListener implements EventSubscriberInterface
{

    /**
     * @var Example
     */
    private $message;

    public static function getSubscribedEvents()
    {
        return array(
            'beforeExample' => array('exampleMessage', -20),
            'afterExample'  => array('exampleMessage', -20),
            'afterSuite'    => array('suiteMessage', -20),
        );
    }

    public function __construct(MessageInterface $message)
    {
        $this->message = $message;
    }

    public function exampleMessage(ExampleEvent $event)
    {
        $this->message->setMessage($event->getTitle());
    }

    public function suiteMessage(SuiteEvent $event)
    {
        $this->message->setMessage($event->getResult());
    }
}
