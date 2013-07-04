<?php

namespace spec\PhpSpec\Formatter\Html;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

use PhpSpec\Event\ExampleEvent;
use PhpSpec\Formatter\Html\IO;
use PhpSpec\Formatter\Presenter\PresenterInterface as Presenter;

class ReportFailedItemSpec extends ObjectBehavior
{
    const EVENT_TITLE = 'it does not works';
    const EVENT_MESSAGE = 'oops';
    static $BACKTRACE = array(
        array('line' => 42, 'file' => '/some/path/to/SomeException.php')
    );
    const BACKTRACE = "#42 /some/path/to/SomeException.php";
    const CODE = 'code';

    function let(IO $io, ExampleEvent $event, Presenter $presenter)
    {
        $this->beConstructedWith($io, $event, $presenter);
    }

    function it_writes_a_fail_message_for_a_failing_example(IO $io, ExampleEvent $event, Presenter $presenter)
    {
        $event->getTitle()->willReturn(self::EVENT_TITLE);
        $event->getMessage()->willReturn(self::EVENT_MESSAGE);
        $event->getBacktrace()->willReturn(self::$BACKTRACE);
        $event->getException()->willReturn(new \Exception);
        $io->isVerbose()->willReturn(false);
        $io->write($this->failingTemplate())->shouldBeCalled();
        $presenter->presentException(Argument::cetera())->willReturn(self::CODE);
        $this->write();
    }

    function failingTemplate()
    {
        return '          <script type="text/javascript">makeRed(\'phpspec-header\');</script>
          <script type="text/javascript">makeRed(\'div_group_1\');</script>
          <script type="text/javascript">makeRed(\'example_group_1\');</script>
          <dd class="example failed">
            <span class="failed_spec_name">' . self::EVENT_TITLE . ' (FAILED - ' . self::EVENT_MESSAGE . ')</span>
              <div class="failure" id="failure_1">
                <div class="message"><pre>' . self::EVENT_MESSAGE . '</pre></div>
                <div class="backtrace"><pre>' . self::BACKTRACE . '</pre></div>
                <pre class="php">' . self::CODE . '</pre>
              </div>
          </dd>';
    }
}
