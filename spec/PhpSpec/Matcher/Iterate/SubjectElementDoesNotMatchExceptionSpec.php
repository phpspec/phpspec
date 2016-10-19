<?php

namespace spec\PhpSpec\Matcher\Iterate;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class SubjectElementDoesNotMatchExceptionSpec extends ObjectBehavior
{
    function let()
    {
        $this->beConstructedWith(42, 'subject key', 'subject value', 'expected key', 'expected value');
    }

    function it_is_a_runtime_exception()
    {
        $this->shouldHaveType(\RuntimeException::class);
    }

    function it_has_a_predefined_message()
    {
        $this->getMessage()->shouldReturn('Subject element does not match with expected element.');
    }

    function it_contains_the_details_of_matched_element()
    {
        $this->getElementNumber()->shouldReturn(42);
        $this->getSubjectKey()->shouldReturn('subject key');
        $this->getSubjectValue()->shouldReturn('subject value');
        $this->getExpectedKey()->shouldReturn('expected key');
        $this->getExpectedValue()->shouldReturn('expected value');
    }
}
