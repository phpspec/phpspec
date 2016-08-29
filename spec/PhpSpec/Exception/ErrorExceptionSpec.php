<?php

namespace spec\PhpSpec\Exception;

use PhpSpec\Exception\ErrorException;
use PhpSpec\Exception\Example\SkippingException;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class ErrorExceptionSpec extends ObjectBehavior
{
    function let()
    {
        if (!class_exists('\Error')) {
            throw new SkippingException('The class Error, introduced in PHP 7, does not exist');
        }

        $this->beConstructedWith(new \Error('This is an error'));
    }

    function it_is_an_exception()
    {
        $this->shouldHaveType(\Exception::class);
    }

    function its_message_comes_from_the_error()
    {
        $this->getMessage()->shouldEqual('This is an error');
    }
}
