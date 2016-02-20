<?php

namespace PhpSpec\Listener;

use PhpSpec\Event\ExampleEvent;

interface ExampleListener
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
