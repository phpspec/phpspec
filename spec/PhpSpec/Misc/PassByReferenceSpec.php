<?php

namespace spec\PhpSpec\Misc;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class PassByReferenceSpec extends ObjectBehavior
{
    public function it_will_not_fail_with_pass_by_reference_values()
    {
        $value = ['this is an array'];

        $this->test($value)->shouldReturn($value);
    }
}
