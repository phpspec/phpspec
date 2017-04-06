<?php

namespace spec\PhpSpec\Specification;

use PhpSpec\Specification;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class ErrorSpecificationSpec extends ObjectBehavior
{
    function it_is_a_specification()
    {
        $this->shouldHaveType(Specification::class);
    }
}
