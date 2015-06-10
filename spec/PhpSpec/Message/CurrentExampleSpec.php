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
        $currentExample = new CurrentExample();
        $currentExample->setCurrentExample('test');
        expect($currentExample->getCurrentExample())->toBe('test');
    }

    function it_should_be_null_on_construction()
    {
        $currentExample = new CurrentExample();
        expect($currentExample->getCurrentExample())->toBe(null);
    }
}
