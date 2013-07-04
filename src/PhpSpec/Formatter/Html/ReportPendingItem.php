<?php

namespace PhpSpec\Formatter\Html;

use PhpSpec\Event\ExampleEvent;
use PhpSpec\Formatter\Html\IO;

class ReportPendingItem
{
    private $io;
    private $event;
    static private $pendingExamplesCount = 1;

    public function __construct(IO $io, ExampleEvent $event)
    {
        $this->io = $io;
        $this->event = $event;
    }

    public function write()
    {
        $this->io->write(
            '             <dd class="example not_implemented">
      <span class="not_implemented_spec_name">' . $this->event->getTitle() . '</span>
      <script type="text/javascript">makeYellow(\'phpspec-header\');</script>
      <script type="text/javascript">makeYellow(\'div_group_' . self::$pendingExamplesCount . '\');</script>
      <script type="text/javascript">makeYellow(\'example_group_' . self::$pendingExamplesCount++ . '\');</script>
    </dd>');
    }
}