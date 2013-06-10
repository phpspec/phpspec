<?php

namespace spec\PhpSpec\Formatter;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

use PhpSpec\Event\ExampleEvent;
use PhpSpec\Console\IO;

class HtmlFormatterSpec extends ObjectBehavior
{
    const EVENT_TITLE = 'it works';
    
    function it_prints_the_passed_template_when_example_pass(IO $io, ExampleEvent $event)
    {
        $this->setIO($io);
        $event->getResult()->willReturn(ExampleEvent::PASSED);
        $event->getTitle()->willReturn(self::EVENT_TITLE);

        $io->write($this->passingTemplate(self::EVENT_TITLE))->shouldBeCalled();
        
        $this->afterExample($event);
    }

    function it_prints_the_pending_template_when_example_is_pending(IO $io, ExampleEvent $event)
    {
        $this->setIO($io);
        $event->getResult()->willReturn(ExampleEvent::PENDING);
        $event->getTitle()->willReturn(self::EVENT_TITLE);

        $io->write($this->pendingTemplate(self::EVENT_TITLE, 1))->shouldBeCalled();
        
        $this->afterExample($event);
    }

    function passingTemplate($title)
    {
        return '          <dd class="example passed">' . $title . '</dd>';
    }

    function pendingTemplate($title, $pendingExamplesCount)
    {
        return '             <dd class="example not_implemented">
      <span class="not_implemented_spec_name">' . $title . '</span>
      <script type="text/javascript">makeYellow(\'phpspec-header\');</script>
      <script type="text/javascript">makeYellow(\'div_group_' . $pendingExamplesCount . '\');</script>
      <script type="text/javascript">makeYellow(\'example_group_' . $pendingExamplesCount . '\');</script>
    </dd>';
    }
}
