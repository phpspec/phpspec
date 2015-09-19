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

    function it_remembers_the_typehints_that_are_added()
    {
        $this->add('Foo', '$bar', 'Baz');

        $this->lookup('Foo', '$bar')->shouldReturn('Baz');
    }

    function it_returns_false_for_typehints_that_have_not_been_added()
    {
        $this->lookup('Foo', '$bar')->shouldBe(false);
    }
}
