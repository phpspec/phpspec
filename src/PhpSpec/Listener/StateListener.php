<?php

namespace PhpSpec\Listener;

use PhpSpec\Event\ExampleEvent;
use PhpSpec\Message\Example;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class StateListener implements EventSubscriberInterface {

    public static function getSubscribedEvents() {
        return array(
            'beforeExample' => array('beforeExample', -20),
            'afterExample'  => array('afterExample', -20),
            'afterSuite'    => array('afterSuite', -20),
        );
    }

    public function beforeExample(ExampleEvent $example, Example $message)
    {
        $message->setExampleMessage($example->getTitle());
    }

    public function afterExample(ExampleEvent $example, Example $message)
    {
        $message->setExampleMessage($example->getTitle());
    }

    public function afterSuite(ExampleEvent $example, Example $message)
    {
        $message->setExampleMessage($example->getTitle());
    }
}
