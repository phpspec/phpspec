<?php

namespace spec\PhpSpec\Formatter;

use PhpSpec\Console\ConsoleIO;
use PhpSpec\Event\ExampleEvent;
use PhpSpec\Formatter\Presenter\Presenter;
use PhpSpec\Listener\StatisticsCollector;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class ProgressFormatterSpec extends ObjectBehavior
{
    function let(Presenter $presenter, ConsoleIO $io, StatisticsCollector $stats)
    {
        $this->beConstructedWith($presenter, $io, $stats);
        $io->getBlockWidth()->willReturn(80);
        $io->isDecorated()->willReturn(false);
        $io->writeTemp(Argument::cetera())->should(function () {});
    }

    function it_is_an_event_subscriber()
    {
        $this->shouldHaveType('Symfony\Component\EventDispatcher\EventSubscriberInterface');
    }

    function it_outputs_progress_as_0_when_0_examples_have_run(ExampleEvent $event, ConsoleIO $io, StatisticsCollector $stats)
    {
        $stats->getEventsCount()->willReturn(0);
        $stats->getCountsHash()->willReturn(array(
                'passed'  => 0,
                'pending' => 0,
                'skipped' => 0,
                'failed'  => 0,
                'broken'  => 0,
            ));
        $stats->getTotalSpecs()->willReturn(0);
        $stats->getTotalSpecsCount()->willReturn(0);

        $io->isDecorated()->willReturn(false);
        $io->getBlockWidth()->willReturn(0);

        $expected = '/  skipped: 0%  /  pending: 0%  /  passed: 0%   /  failed: 0%   /  broken: 0%   /  0 examples';
        $io->writeTemp($expected)->shouldBeCalled();

        $this->afterExample($event);
    }

    function it_outputs_progress_as_0_when_0_examples_have_passed(ExampleEvent $event, ConsoleIO $io, StatisticsCollector $stats)
    {
        $stats->getEventsCount()->willReturn(1);
        $stats->getCountsHash()->willReturn(array(
                'passed'  => 1,
                'pending' => 0,
                'skipped' => 0,
                'failed'  => 0,
                'broken'  => 0,
            ));
        $stats->getTotalSpecs()->willReturn(1);
        $stats->getTotalSpecsCount()->willReturn(1);

        $io->isDecorated()->willReturn(false);
        $io->getBlockWidth()->willReturn(0);

        $expected = '/  skipped: 0%  /  pending: 0%  / passed: 100%  /  failed: 0%   /  broken: 0%   /  1 examples';
        $io->writeTemp($expected)->shouldBeCalled();

        $this->afterExample($event);
    }

    function it_outputs_progress_as_100_when_1_of_3_examples_have_passed(ExampleEvent $event, ConsoleIO $io, StatisticsCollector $stats)
    {
        $stats->getEventsCount()->willReturn(1);
        $stats->getCountsHash()->willReturn(array(
            'passed'  => 1,
            'pending' => 0,
            'skipped' => 0,
            'failed'  => 0,
            'broken'  => 0,
        ));
        $stats->getTotalSpecs()->willReturn(1);
        $stats->getTotalSpecsCount()->willReturn(3);

        $io->isDecorated()->willReturn(false);
        $io->getBlockWidth()->willReturn(0);

        $expected = '/  skipped: 0%  /  pending: 0%  / passed: 100%  /  failed: 0%   /  broken: 0%   /  1 examples';
        $io->writeTemp($expected)->shouldBeCalled();

        $this->afterExample($event);
    }

    function it_outputs_progress_as_33_when_3_of_3_examples_have_run_and_one_passed(ExampleEvent $event, ConsoleIO $io, StatisticsCollector $stats)
    {
        $stats->getEventsCount()->willReturn(3);
        $stats->getCountsHash()->willReturn(array(
                'passed'  => 1,
                'pending' => 0,
                'skipped' => 0,
                'failed'  => 2,
                'broken'  => 0,
            ));
        $stats->getTotalSpecs()->willReturn(3);
        $stats->getTotalSpecsCount()->willReturn(3);

        $io->isDecorated()->willReturn(false);
        $io->getBlockWidth()->willReturn(0);

        $expected = '/  skipped: 0%  /  pending: 0%  /  passed: 33%  /  failed: 66%  /  broken: 0%   /  3 examples';
        $io->writeTemp($expected)->shouldBeCalled();

        $this->afterExample($event);
    }
}
