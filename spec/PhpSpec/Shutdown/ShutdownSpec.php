<?php

namespace spec\PhpSpec\Shutdown;

use PhpSpec\Formatter\FatalFormatter;
use PhpSpec\Message\Example;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class ShutdownSpec extends ObjectBehavior
{
    function let(Example $message, FatalFormatter $formatter)
    {
        $this->beConstructedWith($message, $formatter);
    }

    function it_should_update_the_formatter_on_shutdown(Example $message)
    {
        $message->getExampleMessage()->willReturn('Hello');
        $this->updateConsole();
    }
}
