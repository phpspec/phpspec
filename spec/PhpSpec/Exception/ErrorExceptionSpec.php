<?php

namespace spec\PhpSpec\Exception;

use PhpSpec\Exception\ErrorException;
use PhpSpec\Exception\Example\SkippingException;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class ErrorExceptionSpec extends ObjectBehavior
{
    private $error;

    function let()
    {
        if (!class_exists('\Error')) {
            throw new SkippingException('The class Error, introduced in PHP 7, does not exist');
        }

        $this->beConstructedWith($this->error = new \Error('This is an error', 42));
    }

    function it_is_an_exception()
    {
        $this->shouldHaveType(\Exception::class);
    }

    function its_message_is_the_same_as_the_errors()
    {
        $this->getMessage()->shouldEqual('This is an error');
    }

    function its_code_is_the_same_as_the_errors()
    {
        $this->getCode()->shouldEqual(42);
    }

    function its_previous_is_the_error()
    {
        $this->getPrevious()->shouldEqual($this->error);
    }

    function its_line_is_the_same_as_the_errors()
    {
        $this->getLine()->shouldEqual($this->error->getLine());
    }
}
