<?php

namespace spec\PhpSpec\Loader\Transformer;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class InMemoryTypeHintIndexSpec extends ObjectBehavior
{
    function it_is_a_typehint_index()
    {
        $this->shouldHaveType('PhpSpec\Loader\Transformer\TypeHintIndex');
    }
}
