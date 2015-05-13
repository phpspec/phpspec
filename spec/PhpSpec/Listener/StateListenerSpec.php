<?php

namespace spec\PhpSpec\Listener;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class StateListenerSpec extends ObjectBehavior {
    function it_is_initializable() {
        $this->shouldHaveType('PhpSpec\Listener\StateListener');
    }

    function it_should_implement_event_subscriber_interface() {
        $this->shouldHaveType('Symfony\Component\EventDispatcher\EventSubscriberInterface');
    }

    function it_should_call_beforeExample() {
        $this->beforeExample();
    }

    function it_should_call_afterExample()
    {
        $this->afterExample();
    }

    function it_should_call_afterSuite()
    {
        $this->afterSuite();
    }


}
