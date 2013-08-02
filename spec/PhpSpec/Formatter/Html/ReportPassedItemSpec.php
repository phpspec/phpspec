<?php

namespace spec\PhpSpec\Formatter\Html;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

use PhpSpec\Event\ExampleEvent;
use PhpSpec\Formatter\Html\IO;
use PhpSpec\Formatter\Html\Template;

class ReportPassedItemSpec extends ObjectBehavior
{
    const EVENT_TITLE = 'it works';

    function let(Template $template, IO $io, ExampleEvent $event)
    {
        $this->beConstructedWith($template, $io, $event);
    }

    function it_writes_a_pass_message_for_a_passing_example(IO $io, ExampleEvent $event)
    {
        $event->getTitle()->willReturn(self::EVENT_TITLE);
        $io->write($this->passingTemplate(self::EVENT_TITLE))->shouldBeCalled();
        
        $this->write();
    }
    
    private function passingTemplate($title)
    {
        return '          <dd class="example passed">' . $title . '</dd>';
    }
}
