<?php

namespace PhpSpec\Formatter\Html;

use PhpSpec\Event\ExampleEvent;
use PhpSpec\Console\IO;
use PhpSpec\Formatter\Presenter\PresenterInterface as Presenter;

class ReportBrokenItem
{
    private $io;
    private $event;
    static private $brokenExamplesCount = 1;
    private $presenter;

    public function __construct(IO $io, ExampleEvent $event, Presenter $presenter)
    {
        $this->io = $io;
        $this->event = $event;
        $this->presenter = $presenter;
    }

    public function write()
    {
        $code = $this->presenter->presentException($this->event->getException(), $this->io->isVerbose());
        $this->io->write('          <script type="text/javascript">makeRed(\'phpspec-header\');</script>
          <script type="text/javascript">makeRed(\'div_group_' . self::$brokenExamplesCount . '\');</script>
          <script type="text/javascript">makeRed(\'example_group_' . self::$brokenExamplesCount . '\');</script>
          <dd class="example failed">
            <span class="failed_spec_name">' . $this->event->getTitle() . ' (BROKEN - ' . $this->event->getMessage() . ')</span>
              <div class="failure" id="failure_' . self::$brokenExamplesCount++ . '">
                <div class="message"><pre>' . $this->event->getMessage() . '</pre></div>
                <div class="backtrace"><pre>' . $this->formatBacktrace() . '</pre></div>
                <pre class="php">' . $code  . '</pre>
              </div>
          </dd>');
    }
    
    private function formatBacktrace()
    {
        $backtrace = '';
        foreach ($this->event->getBacktrace() as $step) {
            if (isset($step['line']) && isset($step['file'])) {
                $backtrace .= "#{$step['line']} {$step['file']}";
            }
        }
        return $backtrace;
    }
}
