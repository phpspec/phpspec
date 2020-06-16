<?php

namespace PhpSpec\Util;

use PhpSpec\Wrapper\Collaborator;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

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
        if ($eventDispatcher instanceof EventDispatcherInterface
           || $eventDispatcher instanceof Collaborator && $eventDispatcher->getWrappedObject() instanceof EventDispatcherInterface) {
            return $eventDispatcher->dispatch($event, $eventName);
        }

        return $eventDispatcher->dispatch($eventName, $event);
    }
}
