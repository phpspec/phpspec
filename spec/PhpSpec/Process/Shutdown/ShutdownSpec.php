<?php

namespace spec\PhpSpec\Process\Shutdown;

use PhpSpec\Formatter\CurrentExampleWriter;
use PhpSpec\Message\MessageInterface;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class ShutdownSpec extends ObjectBehavior
{
    function let(MessageInterface $message, CurrentExampleWriter $currentExampleWriter)
    {
        $this->beConstructedWith($message, $currentExampleWriter);
    }

    function it_should_register_the_shutdown_method()
    {
        $this->registerShutdown()->shouldReturn(true);
    }

    function it_should_update_the_formatter_on_shutdown(MessageInterface $message)
    {
        $message->getMessage()->willReturn('Hello');
        $this->updateConsole();
    }
}
