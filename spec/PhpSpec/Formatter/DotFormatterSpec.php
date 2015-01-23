<?php

namespace spec\PhpSpec\Formatter;

use PhpSpec\Formatter\Presenter\PresenterInterface;
use PhpSpec\Console\IO;
use PhpSpec\Listener\StatisticsCollector;
use PhpSpec\Event\SuiteEvent;
use PhpSpec\Event\ExampleEvent;
use PhpSpec\ObjectBehavior;
use PhpSpec\Exception\Example\PendingException;
use PhpSpec\Loader\Node\SpecificationNode;
use PhpSpec\Loader\Node\ExampleNode;
use Prophecy\Argument;
use ReflectionFunctionAbstract;

class DotFormatterSpec extends ObjectBehavior
{
    function let(PresenterInterface $presenter, IO $io, StatisticsCollector $stats)
    {
        $this->beConstructedWith($presenter, $io, $stats);
    }

    function it_is_a_console_formatter()
    {
        $this->shouldHaveType('PhpSpec\Formatter\ConsoleFormatter');
    }

    function it_outputs_a_dot_for_a_passed_example(
        ExampleEvent $event,
        IO $io,
        StatisticsCollector $stats
    ) {
        $event->getResult()->willReturn(ExampleEvent::PASSED);

        $this->afterExample($event);

        $io->write("<passed>.</passed>")->shouldHaveBeenCalled();
    }

    function it_outputs_a_p_for_a_pending_example(
        ExampleEvent $event,
        IO $io,
        StatisticsCollector $stats
    ) {
        $event->getResult()->willReturn(ExampleEvent::PENDING);

        $this->afterExample($event);

        $io->write('<pending>P</pending>')->shouldHaveBeenCalled();
    }

    function it_outputs_an_s_for_a_skipped_example(
        ExampleEvent $event,
        IO $io,
        StatisticsCollector $stats
    ) {
        $event->getResult()->willReturn(ExampleEvent::SKIPPED);

        $this->afterExample($event);

        $io->write('<skipped>S</skipped>')->shouldHaveBeenCalled();
    }

    function it_outputs_an_f_for_a_failed_example(
        ExampleEvent $event,
        IO $io,
        StatisticsCollector $stats
    ) {
        $event->getResult()->willReturn(ExampleEvent::FAILED);

        $this->afterExample($event);

        $io->write('<failed>F</failed>')->shouldHaveBeenCalled();
    }

    function it_outputs_a_b_for_a_broken_example(
        ExampleEvent $event,
        IO $io,
        StatisticsCollector $stats
    ) {
        $event->getResult()->willReturn(ExampleEvent::BROKEN);

        $this->afterExample($event);

        $io->write('<broken>B</broken>')->shouldHaveBeenCalled();
    }

    function it_outputs_the_progress_every_50_examples(
        ExampleEvent $exampleEvent,
        SuiteEvent $suiteEvent,
        IO $io,
        StatisticsCollector $stats
    ) {
        $exampleEvent->getResult()->willReturn(ExampleEvent::PASSED);
        $suiteEvent->getSuite()->willReturn(range(1, 100));
        $stats->getEventsCount()->willReturn(50);

        $this->beforeSuite($suiteEvent);
        $this->afterExample($exampleEvent);

        $io->write('  50 / 100')->shouldHaveBeenCalled();
    }

    function it_outputs_exceptions_for_failed_examples(
        SuiteEvent $event,
        ExampleEvent $pendingEvent,
        IO $io,
        StatisticsCollector $stats,
        SpecificationNode $specification,
        ExampleNode $example,
        ReflectionFunctionAbstract $reflectionFunction
    ) {
        $reflectionFunction->getStartLine()->willReturn(37);
        $example->getFunctionReflection()->willReturn($reflectionFunction);
        $example->getTitle()->willReturn('it tests something');
        $pendingEvent->getException()->willReturn(new PendingException());
        $pendingEvent->getSpecification()->willReturn($specification);
        $pendingEvent->getExample()->willReturn($example);

        $stats->getEventsCount()->willReturn(1);
        $stats->getFailedEvents()->willReturn(array());
        $stats->getBrokenEvents()->willReturn(array());
        $stats->getPendingEvents()->willReturn(array($pendingEvent));
        $stats->getSkippedEvents()->willReturn(array());
        $stats->getTotalSpecs()->willReturn(1);

        $stats->getCountsHash()->willReturn(array(
            'passed'  => 0,
            'pending' => 1,
            'skipped' => 0,
            'failed'  => 0,
            'broken'  => 0,
        ));

        $this->afterSuite($event);

        $expected = '<lineno>  37</lineno>  <pending>- it tests something</pending>';
        $io->writeln($expected)->shouldHaveBeenCalled();
    }

    function it_outputs_a_suite_summary(
        SuiteEvent $event,
        IO $io,
        StatisticsCollector $stats
    ) {
        $stats->getEventsCount()->willReturn(1);
        $stats->getFailedEvents()->willReturn(array());
        $stats->getBrokenEvents()->willReturn(array());
        $stats->getPendingEvents()->willReturn(array());
        $stats->getSkippedEvents()->willReturn(array());
        $stats->getTotalSpecs()->willReturn(15);
        $event->getTime()->willReturn(12.345);

        $stats->getCountsHash()->willReturn(array(
            'passed'  => 1,
            'pending' => 0,
            'skipped' => 0,
            'failed'  => 2,
            'broken'  => 0,
        ));

        $this->afterSuite($event);

        $io->writeln('15 specs')->shouldHaveBeenCalled();
        $io->writeln("\n12345ms")->shouldHaveBeenCalled();
        $io->write('1 example ')->shouldHaveBeenCalled();
        $expected = '(<passed>1 passed</passed>, <failed>2 failed</failed>)';
        $io->write($expected)->shouldHaveBeenCalled();
    }
}
