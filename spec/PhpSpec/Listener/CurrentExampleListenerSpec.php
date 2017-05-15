<?php

namespace spec\PhpSpec\Listener;

use PhpSpec\Event\ExampleEvent;
use PhpSpec\Event\SuiteEvent;
use PhpSpec\Message\CurrentExampleTracker;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class CurrentExampleListenerSpec extends ObjectBehavior
{
    function let()
    {
        $currentExample = new CurrentExampleTracker();
        $this->beConstructedWith($currentExample);
    }

    function it_is_initializable()
    {
        $this->shouldHaveType('PhpSpec\Listener\CurrentExampleListener');
    }

    function it_should_implement_event_subscriber_interface()
    {
        $this->shouldHaveType('PhpSpec\Listener\ExampleListener');
        $this->shouldHaveType('PhpSpec\Listener\SuiteListener');
    }

    function it_should_call_beforeCurrentExample(ExampleEvent $example)
    {
        $currentExample = new CurrentExampleTracker();
        $fatalError = 'Fatal error happened before example';
        $example->getTitle()->willReturn($fatalError);
        $currentExample->setCurrentExample($fatalError);
        $this->beforeExample($example);
        $example->getTitle()->shouldHaveBeenCalled();
    }

    function it_should_call_afterCurrentExample(ExampleEvent $example)
    {
        $currentExample = new CurrentExampleTracker();
        $currentExample->setCurrentExample(null);
        $example->getTitle()->willReturn(null);
        $this->afterExample($example);
        $example->getTitle()->shouldNotHaveBeenCalled();
    }

    function it_should_call_afterSuiteEvent(SuiteEvent $example)
    {
        $fatalError = '3';
        $currentExample = new CurrentExampleTracker();
        $currentExample->setCurrentExample("Exited with code: " . $fatalError);
        $example->getResult()->willReturn($fatalError);
        $this->afterSuite($example);
        $example->getResult()->shouldHaveBeenCalled();
    }
}
