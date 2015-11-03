<?php

namespace spec\PhpSpec\Process\Shutdown;

use PhpSpec\Formatter\FatalPresenter;
use PhpSpec\Message\CurrentExampleTracker;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class UpdateConsoleActionSpec extends ObjectBehavior
{
    function let(FatalPresenter $currentExampleWriter)
    {
        $currentExample = new CurrentExampleTracker();
        $this->beConstructedWith($currentExample, $currentExampleWriter);
    }

    function it_should_update_the_console(FatalPresenter $currentExampleWriter)
    {
        $currentExample = new CurrentExampleTracker();
        $error = array('type' => 1, 'message' => 'Hello');
        $currentExample->getCurrentExample('Hello');
        $currentExampleWriter->displayFatal($currentExample, $error)->shouldBeCalled();
        $this->runAction($error);
    }
}
