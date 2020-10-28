<?php

namespace spec\PhpSpec\Exception\Example;

use PhpSpec\ObjectBehavior;

class MethodFailureExceptionSpec extends ObjectBehavior
{
    /**
     * @var mixed
     */
    private $subject;

    function let()
    {
        $this->subject = new \stdClass();
        $method = 'methodName';

        $this->beConstructedWith('Method failure', 2, 5, $this->subject, $method);
    }

    function it_is_not_equal()
    {
        $this->shouldBeAnInstanceOf('PhpSpec\Exception\Example\NotEqualException');
    }

    function it_is_failure()
    {
        $this->shouldBeAnInstanceOf('PhpSpec\Exception\Example\FailureException');
    }

    function it_provides_a_link_to_the_subject()
    {
        $this->getSubject()->shouldReturn($this->subject);
    }

    function it_provides_a_link_to_the_method()
    {
        $this->getMethod()->shouldReturn('methodName');
    }
}
