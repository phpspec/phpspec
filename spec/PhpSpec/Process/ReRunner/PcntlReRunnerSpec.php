<?php

namespace spec\PhpSpec\Process\ReRunner;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class PcntlReRunnerSpec extends ObjectBehavior
{
    function it_is_a_rerunner()
    {
        $this->shouldHaveType('PhpSpec\Process\ReRunner');
    }
}
