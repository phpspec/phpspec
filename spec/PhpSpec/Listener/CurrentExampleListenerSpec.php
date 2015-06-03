<?php

namespace spec\PhpSpec\Listener;

use PhpSpec\Event\ExampleEvent;
use PhpSpec\Event\SuiteEvent;
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
        $localMessage = 'in after example';
        $example->getTitle()->willReturn($localMessage);
        $message->setCurrentExample('After the example ' . $localMessage)->shouldBeCalled();
        $this->afterExampleMessage($example);
    }

    function it_should_call_afterSuite(SuiteEvent $example, CurrentExample $message)
    {
        $localMessage = '0';
        $example->getResult()->willReturn($localMessage);
        $message->setCurrentExample($localMessage)->shouldBeCalled();
        $this->suiteMessage($example);
    }

}
