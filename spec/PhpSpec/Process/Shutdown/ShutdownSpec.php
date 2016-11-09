<?php

namespace spec\PhpSpec\Process\Shutdown;

use PhpSpec\ObjectBehavior;
use PhpSpec\Process\Shutdown\Shutdown;
use PhpSpec\Process\Shutdown\ShutdownAction;
use Prophecy\Argument;

class ShutdownSpec extends ObjectBehavior
{
    function it_is_initializable()
    {
        $this->shouldHaveType(Shutdown::class);
    }

    function it_runs_no_shutdown_actions_when_there_is_no_error(ShutdownAction $action)
    {
        $action->runAction(Argument::any())->shouldNotBeCalled();

        $this->registerAction($action);
        $this->runShutdown();
    }
}
