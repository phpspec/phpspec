<?php

namespace spec\PhpSpec\Process\Shutdown;

use PhpSpec\ObjectBehavior;
use PhpSpec\Process\Shutdown\UpdateConsoleAction;
use Prophecy\Argument;

class ShutdownSpec extends ObjectBehavior
{
    function it_returns_the_count_of_the_actions_registered(UpdateConsoleAction $action)
    {
        $this->count()->shouldReturn(0);
        $this->registerAction($action);
        $this->count()->shouldReturn(1);
    }

    function it_runs_register_shutdown(UpdateConsoleAction $action)
    {
        $action->runAction()->shouldBeCalled();
        $this->registerAction($action);
        $this->registerShutdown();
    }
}
