<?php

namespace PhpSpec\Util;

/**
 * @internal
 */
trait DispatchTrait
{
    private function dispatch(object $eventDispatcher, object $event, string $eventName) : object
    {
        return $eventDispatcher->dispatch($event, $eventName);
    }
}
