<?php

namespace spec\PhpSpec\Process\Shutdown;

use PhpSpec\Formatter\FatalPresenterInterface;
use PhpSpec\Message\CurrentExample;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class UpdateConsoleActionSpec extends ObjectBehavior
{

    function let(FatalPresenterInterface $currentExampleWriter)
    {
        $currentExample = new CurrentExample();
        $this->beConstructedWith($currentExample, $currentExampleWriter);
    }

    function it_should_update_the_console(FatalPresenterInterface $currentExampleWriter)
    {
        $currentExample = new CurrentExample();
        $error = array('type' => 1, 'message' => 'Hello');
        $currentExample->getCurrentExample('Hello');
        $currentExampleWriter->displayFatal($currentExample, $error)->shouldBeCalled();
        $this->runAction($error);
    }
}
