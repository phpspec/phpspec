<?php

namespace spec\PhpSpec\Formatter\Html;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

use PhpSpec\Event\ExampleEvent;
use PhpSpec\Console\IO;

class ReportPendingItemSpec extends ObjectBehavior
{
    const EVENT_TITLE = 'it works';

    function let(IO $io, ExampleEvent $event)
    {
        $this->beConstructedWith($io, $event);
    }

    function it_writes_a_pass_message_for_a_passing_example(IO $io, ExampleEvent $event)
    {
        $event->getTitle()->willReturn(self::EVENT_TITLE);
        $io->write($this->pendingTemplate(self::EVENT_TITLE, 1))->shouldBeCalled();
        
        $this->write();
    }
    
    private function pendingTemplate($title, $pendingExamplesCount)
    {
        return '             <dd class="example not_implemented">
      <span class="not_implemented_spec_name">' . $title . '</span>
      <script type="text/javascript">makeYellow(\'phpspec-header\');</script>
      <script type="text/javascript">makeYellow(\'div_group_' . $pendingExamplesCount . '\');</script>
      <script type="text/javascript">makeYellow(\'example_group_' . $pendingExamplesCount . '\');</script>
    </dd>';
    }
}
