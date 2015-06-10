<?php

namespace spec\PhpSpec\Listener;

use PhpSpec\Event\ExampleEvent;
use PhpSpec\Event\SuiteEvent;
use PhpSpec\Message\CurrentExample;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class CurrentExampleListenerSpec extends ObjectBehavior
{
    function let(CurrentExample $currentExample)
    {
        $this->beConstructedWith($currentExample);
    }

    function it_is_initializable()
    {
        $this->shouldHaveType('PhpSpec\Listener\CurrentExampleListener');
    }

    function it_should_implement_event_subscriber_interface()
    {
        $this->shouldHaveType('Symfony\Component\EventDispatcher\EventSubscriberInterface');
    }

    function it_should_call_beforeCurrentExample(ExampleEvent $example, CurrentExample $currentExample)
    {
        $fatalError = 'Fatal error happened before example';
        $example->getTitle()->willReturn($fatalError);
        $currentExample->setCurrentExample($fatalError)->shouldBeCalled();
        $this->beforeCurrentExample($example);

    }

    function it_should_call_afterCurrentExample(ExampleEvent $example, CurrentExample $currentExample)
    {
        $currentExample->setCurrentExample(null)->shouldBeCalled();
        $this->afterCurrentExample($example);
    }

    function it_should_call_afterSuiteEvent(SuiteEvent $example, CurrentExample $currentExample)
    {
        $fatalError = '3';
        $example->getResult()->willReturn($fatalError);
        $currentExample->setCurrentExample("Exited with code: " . $fatalError)->shouldBeCalled();
        $this->afterSuiteEvent($example);
    }
}
