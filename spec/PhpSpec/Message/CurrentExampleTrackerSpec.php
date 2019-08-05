<?php

namespace spec\PhpSpec\Message;

use PhpSpec\ObjectBehavior;

class CurrentExampleTrackerSpec extends ObjectBehavior
{
    function it_is_initializable()
    {
        $this->shouldHaveType('PhpSpec\Message\CurrentExampleTracker');
    }

    function it_should_set_a_message()
    {
        $this->setCurrentExample('test');
        $this->getCurrentExample()->shouldBe('test');
    }

    function it_should_be_null_on_construction()
    {
        $this->getCurrentExample()->shouldBe(null);
    }
}
