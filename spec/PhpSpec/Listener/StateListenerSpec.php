<?php

namespace spec\PhpSpec\Listener;

use PhpSpec\Event\ExampleEvent;
use PhpSpec\Event\SuiteEvent;
use PhpSpec\Message\MessageInterface;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class StateListenerSpec extends ObjectBehavior
{

    function let(MessageInterface $message)
    {
        $this->beConstructedWith($message);
    }

    function it_is_initializable()
    {
        $this->shouldHaveType('PhpSpec\Listener\StateListener');
    }

    function it_should_implement_event_subscriber_interface()
    {
        $this->shouldHaveType('Symfony\Component\EventDispatcher\EventSubscriberInterface');
    }

    function it_should_call_beforeExample(ExampleEvent $example, MessageInterface $message)
    {
        $this->exampleMessage($example, $message);
    }

    function it_should_call_afterExample(ExampleEvent $example, MessageInterface $message)
    {
        $this->exampleMessage($example, $message);
    }

    function it_should_call_afterSuite(SuiteEvent $example, MessageInterface $message)
    {
        $this->suiteMessage($example, $message);
    }


}
