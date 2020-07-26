<?php

namespace spec\PhpSpec\Exception\Example;

use PhpSpec\ObjectBehavior;

class NotEqualExceptionSpec extends ObjectBehavior
{
    function let()
    {
        $this->beConstructedWith('Not equal', 2, 5);
    }

    function it_is_failure()
    {
        $this->shouldBeAnInstanceOf('PhpSpec\Exception\Example\FailureException');
    }

    function it_provides_a_link_to_expected()
    {
        $this->getExpected()->shouldReturn(2);
    }

    function it_provides_a_link_to_actual()
    {
        $this->getActual()->shouldReturn(5);
    }

    function it_has_no_link_to_the_subject_by_default()
    {
        $this->getSubject()->shouldReturn(null);
    }

    function it_has_no_link_to_the_method_by_default()
    {
        $this->getMethod()->shouldReturn(null);
    }

    function it_provides_a_link_to_the_subject_if_present()
    {
        $subject = new \stdClass();
        $this->beConstructedWith('Not equal', 2, 5, $subject);

        $this->getSubject()->shouldReturn($subject);
    }

    function it_provides_a_link_to_the_method_if_present()
    {
        $method = 'methodName';
        $this->beConstructedWith('Not equal', 2, 5, null, $method);

        $this->getMethod()->shouldReturn($method);
    }
}
