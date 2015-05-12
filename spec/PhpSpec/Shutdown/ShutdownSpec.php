<?php

namespace spec\PhpSpec\Shutdown;

use PhpSpec\ObjectBehavior;
use PhpSpec\Shutdown\Shutdown;
use Prophecy\Argument;

class ShutdownSpec extends ObjectBehavior
{
    function it_is_initializable()
    {
        $this->shouldHaveType('PhpSpec\Shutdown\Shutdown');
    }

    function it_should_register_a_shutdown_method()
    {
        $this::register()->shouldReturn(null);
    }

    function it_should_update_the_formatter_on_shutdown()
    {
        //update
    }
}
