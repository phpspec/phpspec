<?php

namespace PhpSpec\Listener;

use PhpSpec\Event\ExampleEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

interface ExampleListener extends EventSubscriberInterface

{
    /**
     * @param ExampleEvent $event
     */
    public function beforeExample(ExampleEvent $event);

    /**
     * @param ExampleEvent $event
     */
    public function afterExample(ExampleEvent $event);
}
