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
        $this->registerShutdown();
        expect(ini_get('display_errors'))->toBe('0');
        expect(error_reporting())->toBe(8);
    }

    function it_should_update_the_formatter_on_shutdown(MessageInterface $message, CurrentExampleWriter $currentExampleWriter)
    {
        $message->getMessage()->willReturn('Hello');
        $currentExampleWriter->displayFatal($message)->shouldBeCalled();
        $this->updateConsole();
    }
}
