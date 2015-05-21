<?php

namespace spec\PhpSpec\Shutdown;

use PhpSpec\Message\Example;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class ShutdownSpec extends ObjectBehavior
{
    function let(Example $message)
    {
        $this->beConstructedWith($message);
    }

    function it_should_update_the_formatter_on_shutdown(Example $message)
    {
        $message->getExampleMessage()->willReturn('Hello');
        $this->updateConsole();
    }
}
