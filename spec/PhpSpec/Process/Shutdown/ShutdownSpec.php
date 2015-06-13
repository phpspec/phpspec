<?php

namespace spec\PhpSpec\Process\Shutdown;

use PhpSpec\ObjectBehavior;
use PhpSpec\Process\Shutdown\ShutdownActionInterface;
use Prophecy\Argument;

class ShutdownSpec extends ObjectBehavior
{
    function it_runs_register_shutdown(ShutdownActionInterface $action)
    {
        $action->runAction()->shouldBeCalled();
        $this->registerAction($action);
        $this->runShutdown();
    }
}
