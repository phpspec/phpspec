<?php

namespace spec\PhpSpec\Process\Shutdown;

use PhpSpec\Formatter\CurrentExampleWriter;
use PhpSpec\Formatter\WriterInterface;
use PhpSpec\Message\CurrentExample;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class UpdateConsoleActionSpec extends ObjectBehavior
{

    function let(CurrentExample $currentExample, WriterInterface $currentExampleWriter)
    {
        $this->beConstructedWith($currentExample, $currentExampleWriter);
    }

    function it_should_update_the_console(CurrentExample $currentExample, WriterInterface $currentExampleWriter)
    {
        $error = array('type' => 1, 'message' => 'Hello');
        $currentExample->getCurrentExample()->willReturn('Hello');
        $currentExampleWriter->displayFatal($currentExample, $error)->shouldBeCalled();
        $this->runAction($error);
    }
}
