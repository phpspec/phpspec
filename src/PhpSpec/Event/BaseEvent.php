<?php

namespace PhpSpec\Event;

use Symfony\Component\EventDispatcher\Event as OldEvent;
use Symfony\Contracts\EventDispatcher\Event as ContractEvent;

class BaseEvent extends ContractEvent
{
}
