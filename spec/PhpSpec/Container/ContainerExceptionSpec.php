<?php

namespace spec\PhpSpec\Container;

use Interop\Container\Exception\ContainerException;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class ContainerExceptionSpec extends ObjectBehavior
{
    function it_is_a_runtime_exception()
    {
        $this->shouldHaveType(\RuntimeException::class);
    }

    function it_is_a_container_interop_compliant_exception()
    {
        $this->shouldHaveType(ContainerException::class);
    }
}
