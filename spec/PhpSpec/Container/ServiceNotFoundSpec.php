<?php

namespace spec\PhpSpec\Container;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Interop\Container\Exception\NotFoundException;

class ServiceNotFoundSpec extends ObjectBehavior
{
    function let()
    {
        $this->beConstructedThrough('constructFromServiceId', ['wrong-service-id']);
    }

    function it_is_an_invalid_argument_exception()
    {
        $this->shouldHaveType(\InvalidArgumentException::class);
    }

    function it_is_a_container_interop_compliant_exception()
    {
        $this->shouldHaveType(NotFoundException::class);
    }

    function it_has_a_nice_message()
    {
        $this->getMessage()->shouldBe('Service "wrong-service-id" not found in container');
    }
}
