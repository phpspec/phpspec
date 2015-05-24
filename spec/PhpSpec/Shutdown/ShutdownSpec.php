<?php

namespace spec\PhpSpec\Shutdown;

use PhpSpec\Formatter\FatalFormatter;
use PhpSpec\Message\MessageInterface;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class ShutdownSpec extends ObjectBehavior
{
    function let(MessageInterface $message, FatalFormatter $formatter)
    {
        $this->beConstructedWith($message, $formatter);
    }

    function it_should_update_the_formatter_on_shutdown(MessageInterface $message)
    {
        $message->getMessage()->willReturn('Hello');
        $this->updateConsole();
    }
}
