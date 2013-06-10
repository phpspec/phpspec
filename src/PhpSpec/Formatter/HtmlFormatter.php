<?php

namespace PhpSpec\Formatter;

use PhpSpec\Event\ExampleEvent;

class HtmlFormatter extends BasicFormatter
{
    public function afterExample(ExampleEvent $event)
    {
        $io = $this->getIO();

        switch($event->getResult()) {
            case ExampleEvent::PASSED :
                $io->write(($this->passedTemplateOutput($event->getTitle())));
                break;
            case ExampleEvent::PENDING :
                static $pendingExamplesCount = 1;
                $io->write(sprintf($this->pendingTemplateOutput(
                    $event->getTitle(),
                    $pendingExamplesCount++
                )));
                break;
        }
    }

    private function passedTemplateOutput($title)
    {
        return sprintf('          <dd class="example passed">%s</dd>', $title);
    }

    private function pendingTemplateOutput($title, $pendingExamplesCount)
    {
        return '             <dd class="example not_implemented">
      <span class="not_implemented_spec_name">' . $title . '</span>
      <script type="text/javascript">makeYellow(\'phpspec-header\');</script>
      <script type="text/javascript">makeYellow(\'div_group_' . $pendingExamplesCount . '\');</script>
      <script type="text/javascript">makeYellow(\'example_group_' . $pendingExamplesCount . '\');</script>
    </dd>';
    }
}
