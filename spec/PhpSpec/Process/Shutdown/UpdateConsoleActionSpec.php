<?php

namespace spec\PhpSpec\Process\Shutdown;

use PhpSpec\Formatter\CurrentExampleWriter;
use PhpSpec\Message\CurrentExample;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class UpdateConsoleActionSpec extends ObjectBehavior
{

    function let(CurrentExample $message, CurrentExampleWriter $currentExampleWriter)
    {
        $this->beConstructedWith($message, $currentExampleWriter);
    }

    function it_is_initializable()
    {
        $this->shouldHaveType('PhpSpec\Process\Shutdown\UpdateConsoleAction');
    }

    function it_should_extend_shutdown_action_interface()
    {
        $this->shouldHaveType('PhpSpec\Process\Shutdown\ShutdownActionInterface');
    }

    function it_should_update_the_console(CurrentExample $message, CurrentExampleWriter $currentExampleWriter)
    {
        $message->getCurrentExample()->willReturn('Hello');
        $currentExampleWriter->displayFatal($message)->shouldBeCalled();
        $this->runAction();
    }
}
