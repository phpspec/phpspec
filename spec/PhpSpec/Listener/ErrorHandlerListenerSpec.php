<?php

namespace spec\PhpSpec\Listener;

use PhpSpec\Event\ExampleEvent;
use PhpSpec\Loader\Node\ExampleNode;
use PhpSpec\ObjectBehavior;
use PhpSpec\Process\ErrorHandler;
use Prophecy\Argument;
use ReflectionClass;
use ReflectionMethod;

class ErrorHandlerListenerSpec extends ObjectBehavior
{
    function let(
        ErrorHandler $handler, ExampleEvent $exampleEvent, ExampleNode $exampleNode,
        ReflectionMethod $reflectedFunction, ReflectionClass $reflectedClass
    )
    {
        $this->beConstructedWith($handler);

        $exampleEvent->getExample()->willReturn($exampleNode);
        $exampleNode->getFunctionReflection()->willReturn($reflectedFunction);
        $reflectedFunction->getDeclaringClass()->willReturn($reflectedClass);
        $reflectedFunction->getName()->willReturn('it_is_an_example');
        $reflectedClass->getName()->willReturn('MySpec');
    }

    function it_tells_errorhandler_which_example_is_about_to_be_run(ExampleEvent $exampleEvent, ErrorHandler $handler)
    {
        $this->beforeExample($exampleEvent);

        $handler->setCurrentExample('MySpec', 'it_is_an_example')->shouldhaveBeenCalled();
    }

    function it_clears_the_example_after_execution(ExampleEvent $exampleEvent, ErrorHandler $handler)
    {
        $this->afterExample($exampleEvent);

        $handler->clearCurrentExample()->shouldHaveBeenCalled();
    }
}
