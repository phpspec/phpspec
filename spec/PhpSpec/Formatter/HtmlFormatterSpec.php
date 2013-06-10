<?php

namespace spec\PhpSpec\Formatter;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

use PhpSpec\Event\ExampleEvent;
use PhpSpec\Console\IO;

class HtmlFormatterSpec extends ObjectBehavior
{
    const PASSED_TEMPLATE_OUTPUT = '          <dd class="example passed">it works</dd>';
    
    function it_prints_the_passed_template_when_example_pass(IO $io, ExampleEvent $event)
    {
        $this->setIO($io);
        $event->getResult()->willReturn(ExampleEvent::PASSED);
        $event->getTitle()->willReturn('it works');
        $io->write(self::PASSED_TEMPLATE_OUTPUT)->shouldBeCalled();
        
        $this->afterExample($event);
    }
}
