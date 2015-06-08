<?php

namespace spec\PhpSpec\Formatter;

use PhpSpec\Console\IO;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class CurrentExampleWriterSpec extends ObjectBehavior
{

    function let(IO $io)
    {
        $this->beConstructedWith($io);
    }

    function it_is_initializable()
    {
        $this->shouldHaveType('PhpSpec\Formatter\CurrentExampleWriter');
    }
}
