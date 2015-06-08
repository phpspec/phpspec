<?php

namespace spec\PhpSpec\Message;

use PhpSpec\Message\CurrentExample;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class CurrentExampleSpec extends ObjectBehavior
{
    function it_is_initializable()
    {
        $this->shouldHaveType('PhpSpec\Message\CurrentExample');
    }

    function it_should_set_a_message()
    {
        $message = new CurrentExample();
        $message->setCurrentExample('test');
        expect($message->getCurrentExample())->toBe('test');
    }

    function it_should_be_null_on_construction()
    {
        $message = new CurrentExample();
        expect($message->getCurrentExample())->toBe(null);
    }
}
