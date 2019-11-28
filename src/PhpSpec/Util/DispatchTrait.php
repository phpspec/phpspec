<?php

namespace PhpSpec\Util;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\LegacyEventDispatcherProxy;;

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
        // LegacyEventDispatcherProxy exists in Symfony >= 4.3
        if (class_exists(LegacyEventDispatcherProxy::class)) {
            return $eventDispatcher->dispatch($event, $eventName);
        }

        return $eventDispatcher->dispatch($eventName, $event);
    }
}
