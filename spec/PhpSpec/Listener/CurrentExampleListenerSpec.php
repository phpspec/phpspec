<?php

namespace spec\PhpSpec\Listener;

use PhpSpec\Event\ExampleEvent;
use PhpSpec\Message\CurrentExample;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class CurrentExampleListenerSpec extends ObjectBehavior
{

    function let(CurrentExample $message)
    {
        $this->beConstructedWith($message);
    }

    function it_is_initializable()
    {
        $this->shouldHaveType('PhpSpec\Listener\CurrentExampleListener');
    }

    function it_should_implement_event_subscriber_interface()
    {
        $this->shouldHaveType('Symfony\Component\EventDispatcher\EventSubscriberInterface');
    }

    function it_should_call_beforeExample(ExampleEvent $example, CurrentExample $message)
    {
        $localMessage = 'in before example';
        $example->getTitle()->willReturn($localMessage);
        $message->setCurrentExample($localMessage)->shouldBeCalled();
        $this->beforeExampleMessage($example);

    }

    function it_should_call_afterExample(ExampleEvent $example, CurrentExample $message)
    {
        $message->setCurrentExample("")->shouldBeCalled();
        $this->afterExampleMessage($example);
    }

}
