<?php

namespace spec\PhpSpec\Message;

use PhpSpec\Message\CurrentExampleTracker;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class CurrentExampleTrackerSpec extends ObjectBehavior
{
    function it_is_initializable()
    {
        $this->shouldHaveType('PhpSpec\Message\CurrentExampleTracker');
    }

    function it_should_set_a_message()
    {
        $currentExample = new CurrentExampleTracker();
        $currentExample->setCurrentExample('test');
        expect($currentExample->getCurrentExample())->toBe('test');
    }

    function it_should_be_null_on_construction()
    {
        $currentExample = new CurrentExampleTracker();
        expect($currentExample->getCurrentExample())->toBe(null);
    }
}
