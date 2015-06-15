<?php

namespace spec\PhpSpec\Formatter;

use PhpSpec\Console\IO;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class FatalErrorWriterSpec extends ObjectBehavior
{
    function let(IO $io)
    {
        $this->beConstructedWith($io);
    }

    function it_is_initializable()
    {
        $this->shouldHaveType('PhpSpec\Formatter\FatalErrorWriter');
    }

    function it_implements_writer_interface()
    {
        $this->shouldHaveType('PhpSpec\Formatter\WriterInterface');
    }
}
