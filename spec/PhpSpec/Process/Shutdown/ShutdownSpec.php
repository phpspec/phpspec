<?php

namespace spec\PhpSpec\Process\Shutdown;

use PhpSpec\ObjectBehavior;
use PhpSpec\Process\Shutdown\ShutdownActionInterface;
use Prophecy\Argument;

class ShutdownSpec extends ObjectBehavior
{
    function it_returns_the_count_of_the_actions_registered(ShutdownActionInterface $action)
    {
        $this->count()->shouldReturn(0);
        $this->registerAction($action);
        $this->count()->shouldReturn(1);
    }

    function it_runs_register_shutdown(ShutdownActionInterface $action)
    {
        $action->runAction()->shouldBeCalled();
        $this->registerAction($action);
        $this->runShutdown();
    }
}
