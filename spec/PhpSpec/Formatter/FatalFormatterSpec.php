<?php

namespace spec\PhpSpec\Formatter;

use PhpSpec\Console\IO;
use PhpSpec\Formatter\Presenter\PresenterInterface;
use PhpSpec\Listener\StatisticsCollector;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class FatalFormatterSpec extends ObjectBehavior
{

    function let(PresenterInterface $presenter, IO $io, StatisticsCollector $stats)
    {
        $this->beConstructedWith($presenter, $io, $stats);
    }

    function it_is_initializable()
    {
        $this->shouldHaveType('PhpSpec\Formatter\FatalFormatter');
    }
}
