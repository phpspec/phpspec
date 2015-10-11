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

    function it_outputs_plan_on_aftersuite_event(SuiteEvent $suiteEvent, ExampleEvent $exampleEvent, ExampleNode $example, IO $io, StatisticsCollector $stats)
    {
        $stats->getEventsCount()->willReturn(3);
        $exampleEvent->getExample()->willReturn($example);
        $example->getTitle()->willReturn('foobar');
        $exampleEvent->getResult()->willReturn(0);

        $this->afterExample($exampleEvent);
        $this->afterSuite($suiteEvent);

        $io->writeln('1..3')->shouldHaveBeenCalled();
    }

    function it_outputs_progress_on_afterexample_event(SpecificationEvent $specEvent, ExampleEvent $exampleEvent, ExampleNode $example, SpecificationNode $spec, IO $io, StatisticsCollector $stats)
    {
        $specEvent->getSpecification()->willReturn($spec);
        $exampleEvent->getExample()->willReturn($example);
        $exampleEvent->getResult()->willReturn(ExampleEvent::PASSED);

        $example->getTitle()->willReturn('it foobar');
        $spec->getTitle()->willReturn('spec1');
        $this->beforeSpecification($specEvent);
        $this->afterExample($exampleEvent);

        $example->getTitle()->willReturn('its foobar');
        $spec->getTitle()->willReturn('spec2');
        $this->beforeSpecification($specEvent);
        $this->afterExample($exampleEvent);

        $expected1 = 'ok 1 - spec1: foobar';
        $expected2 = 'ok 2 - spec2: foobar';
        $io->writeln($expected1)->shouldHaveBeenCalled();
        $io->writeln($expected2)->shouldHaveBeenCalled();
    }

    function it_outputs_failure_progress_on_afterexample_event(SpecificationEvent $specEvent, ExampleEvent $exampleEvent, ExampleNode $example, SpecificationNode $spec, IO $io, StatisticsCollector $stats)
    {
        $specEvent->getSpecification()->willReturn($spec);
        $exampleEvent->getExample()->willReturn($example);
        $example->getTitle()->willReturn('foobar');
        $exampleEvent->getResult()->willReturn(ExampleEvent::FAILED);
        $exampleEvent->getException()->willReturn(new \Exception('Something failed.'));

        $spec->getTitle()->willReturn('spec1');
        $this->beforeSpecification($specEvent);
        $this->afterExample($exampleEvent);

        $expected = "not ok 1 - spec1: foobar\n  ---\n  message: 'Something failed.'\n  severity: fail\n  ...";
        $io->writeln($expected)->shouldHaveBeenCalled();
    }

    function it_outputs_skip_progress_on_afterexample_event(SpecificationEvent $specEvent, ExampleEvent $exampleEvent, ExampleNode $example, SpecificationNode $spec, IO $io, StatisticsCollector $stats)
    {
        $specEvent->getSpecification()->willReturn($spec);
        $exampleEvent->getExample()->willReturn($example);
        $example->getTitle()->willReturn('foobar');
        $exampleEvent->getResult()->willReturn(ExampleEvent::SKIPPED);
        $exampleEvent->getException()->willReturn(new \Exception('no reason'));

        $spec->getTitle()->willReturn('spec1');
        $this->beforeSpecification($specEvent);
        $this->afterExample($exampleEvent);

        $expected = 'ok 1 - spec1: foobar # SKIP no reason';
        $io->writeln($expected)->shouldHaveBeenCalled();
    }

    function it_outputs_todo_progress_on_afterexample_event(SpecificationEvent $specEvent, ExampleEvent $exampleEvent, ExampleNode $example, SpecificationNode $spec, IO $io, StatisticsCollector $stats)
    {
        $specEvent->getSpecification()->willReturn($spec);
        $exampleEvent->getExample()->willReturn($example);
        $example->getTitle()->willReturn('foobar');
        $exampleEvent->getResult()->willReturn(ExampleEvent::PENDING);
        $exampleEvent->getException()->willReturn(new \Exception("no\nreason"));

        $spec->getTitle()->willReturn('spec1');
        $this->beforeSpecification($specEvent);
        $this->afterExample($exampleEvent);

        $expected = "not ok 1 - spec1: foobar # TODO no / reason\n  ---\n  severity: todo\n  ...";
        $io->writeln($expected)->shouldHaveBeenCalled();
    }

    function it_outputs_broken_progress_on_afterexample_event(SpecificationEvent $specEvent, ExampleEvent $exampleEvent, ExampleNode $example, SpecificationNode $spec, IO $io, StatisticsCollector $stats)
    {
        $specEvent->getSpecification()->willReturn($spec);
        $exampleEvent->getExample()->willReturn($example);
        $example->getTitle()->willReturn('foobar');
        $exampleEvent->getResult()->willReturn(ExampleEvent::BROKEN);
        $exampleEvent->getException()->willReturn(new \Exception("Something broke's.\nIt hurts."));

        $spec->getTitle()->willReturn('spec1');
        $this->beforeSpecification($specEvent);
        $this->afterExample($exampleEvent);

        $expected = "not ok 1 - spec1: foobar\n  ---\n  message: \"Something broke's.\\nIt hurts.\"\n  severity: fail\n  ...";
        $io->writeln($expected)->shouldHaveBeenCalled();
    }

    function it_outputs_undefined_progress_on_afterexample_event(SpecificationEvent $specEvent, ExampleEvent $exampleEvent, ExampleNode $example, SpecificationNode $spec, IO $io, StatisticsCollector $stats)
    {
        $specEvent->getSpecification()->willReturn($spec);
        $exampleEvent->getExample()->willReturn($example);
        $example->getTitle()->willReturn('foobar');
        $exampleEvent->getResult()->willReturn(999);

        $spec->getTitle()->willReturn('spec1');
        $this->beforeSpecification($specEvent);
        $this->afterExample($exampleEvent);

        $expected = "not ok 1 - spec1: foobar\n  ---\n  message: 'The example result type was unknown to formatter'\n  severity: fail\n  ...";
        $io->writeln($expected)->shouldHaveBeenCalled();
    }
}
