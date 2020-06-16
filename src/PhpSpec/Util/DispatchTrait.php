<?php

namespace PhpSpec\Util;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\LegacyEventDispatcherProxy;

/**
 * @internal
 */
trait DispatchTrait
{
    /**
     * @param EventDispatcherInterface $eventDispatcher
     * @param object $event
     * @param string $eventName
     */
    private function dispatch($eventDispatcher, $event, $eventName)
    {
        // EventDispatcherInterface contract implemented in Symfony >= 4.3
        if ($eventDispatcher instanceof \Symfony\Contracts\EventDispatcher\EventDispatcherInterface) {
            return $eventDispatcher->dispatch($event, $eventName);
        }

        return $eventDispatcher->dispatch($eventName, $event);
    }
}
