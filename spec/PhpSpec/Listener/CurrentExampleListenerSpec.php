<?php

namespace spec\PhpSpec\Listener;

use PhpSpec\Event\ExampleEvent;
use PhpSpec\Event\SuiteEvent;
use PhpSpec\Message\MessageInterface;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class CurrentExampleListenerSpec extends ObjectBehavior
{

    function let(MessageInterface $message)
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

    function it_should_call_beforeExample(ExampleEvent $example, MessageInterface $message)
    {
        $localMessage = 'in before example';
        $example->getTitle()->willReturn($localMessage);
        $message->setMessage($localMessage)->shouldBeCalled();
        $this->beforeExampleMessage($example);

    }

    function it_should_call_afterExample(ExampleEvent $example, MessageInterface $message)
    {
        $localMessage = 'in after example';
        $example->getTitle()->willReturn($localMessage);
        $message->setMessage('After the example ' . $localMessage)->shouldBeCalled();
        $this->afterExampleMessage($example);
    }

    function it_should_call_afterSuite(SuiteEvent $example, MessageInterface $message)
    {
        $localMessage = '0';
        $example->getResult()->willReturn($localMessage);
        $message->setMessage($localMessage)->shouldBeCalled();
        $this->suiteMessage($example);
    }

}
