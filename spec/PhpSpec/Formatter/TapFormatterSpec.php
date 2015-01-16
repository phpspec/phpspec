<?php

namespace spec\PhpSpec\Formatter;

use PhpSpec\Console\IO;
use PhpSpec\Event\SuiteEvent;
use PhpSpec\Event\ExampleEvent;
use PhpSpec\Event\SpecificationEvent;
use PhpSpec\Loader\Node\ExampleNode;
use PhpSpec\Loader\Node\SpecificationNode;
use PhpSpec\Formatter\Presenter\PresenterInterface;
use PhpSpec\Listener\StatisticsCollector;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class TapFormatterSpec extends ObjectBehavior
{
    function let(PresenterInterface $presenter, IO $io, StatisticsCollector $stats)
    {
        $this->beConstructedWith($presenter, $io, $stats);
    }

    function it_is_an_event_subscriber()
    {
        $this->shouldHaveType('Symfony\Component\EventDispatcher\EventSubscriberInterface');
    }

    function it_outputs_version_on_beforesuite_event(SuiteEvent $event, IO $io)
    {
        $this->beforeSuite($event);
        $expected = 'TAP version 13';
        $io->writeln($expected)->shouldHaveBeenCalled();
    }

    function it_outputs_plan_on_aftersuite_event(SuiteEvent $s_event, ExampleEvent $e_event, ExampleNode $example, IO $io, StatisticsCollector $stats)
    {
        $stats->getEventsCount()->willReturn(3);
        $e_event->getExample()->willReturn($example);
        $example->getTitle()->willReturn('foobar');
        $e_event->getResult()->willReturn(0);

        $this->afterExample($e_event);
        $this->afterSuite($s_event);

        $expected = '1..3';
        $io->writeln($expected)->shouldHaveBeenCalled();
    }

    function it_outputs_progress_on_afterexample_event(SpecificationEvent $s_event, ExampleEvent $e_event, ExampleNode $example, SpecificationNode $spec, IO $io, StatisticsCollector $stats)
    {
        $s_event->getSpecification()->willReturn($spec);
        $e_event->getExample()->willReturn($example);
        $example->getTitle()->willReturn('foobar');
        $e_event->getResult()->willReturn(0);

        $spec->getTitle()->willReturn('spec1');
        $this->beforeSpecification($s_event);
        $this->afterExample($e_event);

        $spec->getTitle()->willReturn('spec2');
        $this->beforeSpecification($s_event);
        $this->afterExample($e_event);

        $expected1 = 'ok 1 - spec1: foobar';
        $expected2 = 'ok 2 - spec2: foobar';
        $io->writeln($expected1)->shouldHaveBeenCalled();
        $io->writeln($expected2)->shouldHaveBeenCalled();
    }

    function it_outputs_failure_progress_on_afterexample_event(SpecificationEvent $s_event, ExampleEvent $e_event, ExampleNode $example, SpecificationNode $spec, IO $io, StatisticsCollector $stats)
    {
        $s_event->getSpecification()->willReturn($spec);
        $e_event->getExample()->willReturn($example);
        $example->getTitle()->willReturn('foobar');
        $e_event->getResult()->willReturn(3);

        $spec->getTitle()->willReturn('spec1');
        $this->beforeSpecification($s_event);
        $this->afterExample($e_event);

        $expected = 'not ok 1 - spec1: foobar';
        $io->writeln($expected)->shouldHaveBeenCalled();
    }

    function it_outputs_skip_progress_on_afterexample_event(SpecificationEvent $s_event, ExampleEvent $e_event, ExampleNode $example, SpecificationNode $spec, IO $io, StatisticsCollector $stats)
    {
        $s_event->getSpecification()->willReturn($spec);
        $e_event->getExample()->willReturn($example);
        $example->getTitle()->willReturn('foobar');
        $e_event->getResult()->willReturn(2);
        $e_event->getException()->willReturn(new \Exception('no reason'));

        $spec->getTitle()->willReturn('spec1');
        $this->beforeSpecification($s_event);
        $this->afterExample($e_event);

        $expected = 'ok 1 - spec1: foobar # SKIP no reason';
        $io->writeln($expected)->shouldHaveBeenCalled();
    }

    function it_outputs_todo_progress_on_afterexample_event(SpecificationEvent $s_event, ExampleEvent $e_event, ExampleNode $example, SpecificationNode $spec, IO $io, StatisticsCollector $stats)
    {
        $s_event->getSpecification()->willReturn($spec);
        $e_event->getExample()->willReturn($example);
        $example->getTitle()->willReturn('foobar');
        $e_event->getResult()->willReturn(1);
        $e_event->getException()->willReturn(new \Exception('no reason'));

        $spec->getTitle()->willReturn('spec1');
        $this->beforeSpecification($s_event);
        $this->afterExample($e_event);

        $expected = 'ok 1 - spec1: foobar # TODO no reason';
        $io->writeln($expected)->shouldHaveBeenCalled();
    }
}
