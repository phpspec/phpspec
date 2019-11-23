<?php

namespace PhpSpec\Event;

use Symfony\Component\EventDispatcher\Event as OldEvent;
use Symfony\Contracts\EventDispatcher\Event as ContractEvent;

if (class_exists(ContractEvent::class)) {
    class BaseEvent extends ContractEvent
    {
    }
} else {
    class BaseEvent extends OldEvent
    {
    }
}
