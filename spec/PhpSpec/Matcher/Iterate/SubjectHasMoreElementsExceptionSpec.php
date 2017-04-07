<?php

namespace spec\PhpSpec\Matcher\Iterate;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

final class SubjectHasMoreElementsExceptionSpec extends ObjectBehavior
{
    function it_is_a_length_exception()
    {
        $this->shouldHaveType(\LengthException::class);
    }

    function it_has_a_predefined_message()
    {
        $this->getMessage()->shouldReturn('Subject has more elements than expected.');
    }
}
