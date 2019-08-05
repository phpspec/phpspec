<?php

namespace spec\PhpSpec\Specification;

use PhpSpec\Specification;
use PhpSpec\ObjectBehavior;

class ErrorSpecificationSpec extends ObjectBehavior
{
    function it_is_a_specification()
    {
        $this->shouldHaveType(Specification::class);
    }
}
