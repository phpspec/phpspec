<?php

namespace spec\tests\spec\PhpSpec;

use PhpSpec\ObjectBehavior;
use tests\spec\PhpSpec\TestWithArrayAccess;

class TestWithArrayAccessSpec extends ObjectBehavior
{
    function it_is_initializable()
    {
        $this->shouldHaveType(TestWithArrayAccess::class);
    }

    public function it_implements_arrayAccess_offsetGet()
    {
        $this
            ->offsetGet('foo')
            ->shouldReturn('foo');
    }

    public function it_implements_arrayAccess_offsetExists()
    {
        $this
            ->offsetExists('true')
            ->shouldReturn(true);

        $this
            ->offsetExists('false')
            ->shouldReturn(false);
    }
}
