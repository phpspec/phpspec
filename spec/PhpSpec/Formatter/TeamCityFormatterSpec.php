<?php

namespace spec\PhpSpec\Formatter;

use PhpSpec\Console\ConsoleIO;
use PhpSpec\Event\SpecificationEvent;
use PhpSpec\Event\SuiteEvent;
use PhpSpec\Loader\Node\SpecificationNode;
use PhpSpec\Loader\Suite;
use PhpSpec\Locator\Resource;
use PhpSpec\ObjectBehavior;
use PhpSpec\Event\ExampleEvent;
use PhpSpec\Formatter\Presenter\Presenter;
use PhpSpec\Listener\StatisticsCollector;
use ReflectionClass;

class TeamCityFormatterSpec extends ObjectBehavior
{
    function let(Presenter $presenter, ConsoleIO $io, StatisticsCollector $stats)
    {
        $this->beConstructedWith($presenter, $io, $stats);
    }

    function it_is_an_event_subscriber()
    {
        $this->shouldHaveType('Symfony\Component\EventDispatcher\EventSubscriberInterface');
    }

    function it_outputs_test_suite_started_and_count_for_before_suite(
        SuiteEvent $event,
        ConsoleIO $io,
        ExampleEvent $exampleMock,
        Suite $suiteMock,
        SpecificationNode $specNode
    ) {
        $event->getTime()->willReturn(0.1);
        $specNode->getExamples()->willReturn([$exampleMock]);
        $suiteMock->getSpecifications()->willReturn([$specNode]);
        $event->getSuite()->willReturn($suiteMock);

        $this->beforeSuite($event);

        $io->write("\n##teamcity[testCount")->shouldHaveBeenCalled();
        $io->write(" count='1'")->shouldHaveBeenCalled();

        $io->write("\n##teamcity[testSuiteStarted")->shouldHaveBeenCalled();
        $io->write(" name='PHPSpecTestSuite'")->shouldHaveBeenCalled();
    }

    function it_outputs_test_suite_finished_for_after_suite(
        Presenter $presenter,
        SuiteEvent $event,
        ConsoleIO $io,
        StatisticsCollector $stats
    ) {
        $stats->getTotalSpecs()->willReturn(1);
        $stats->getEventsCount()->willReturn(1);
        $stats->getCountsHash()->willReturn(
            [
                'passed' => 1,
                'pending' => 0,
                'skipped' => 0,
                'failed' => 0,
                'broken' => 0,
            ]
        );
        $event->getTime()->willReturn(0.1);

        $this->beConstructedWith($presenter, $io, $stats);

        $this->afterSuite($event);

        $io->write("\n##teamcity[testSuiteFinished")->shouldHaveBeenCalled();
        $io->write(" name='PHPSpecTestSuite'")->shouldHaveBeenCalled();
        $io->write(" duration='100'")->shouldHaveBeenCalled();

        $io->write("\n\n")->shouldHaveBeenCalled();
        $io->write("1 spec\n")->shouldHaveBeenCalled();
        $io->write("1 example ")->shouldHaveBeenCalled();
        $io->write('(<passed>1 passed</passed>)')->shouldHaveBeenCalled();
        $io->write("\n100ms\n")->shouldHaveBeenCalled();
    }

    function it_outputs_test_suite_started_for_before_specification(
        SpecificationEvent $event,
        ConsoleIO $io,
        SpecificationNode $specNode,
        Resource $resource
    ) {
        $resource->getSpecFilename()->willReturn('spec/PhpSpec/Formatter/TeamCityFormatterSpec.php');
        $resource->getSpecClassname()->willReturn('spec\PhpSpec\Formatter\TeamCityFormatterSpec');
        $specNode->getResource()->willReturn($resource);
        $event->getSpecification()->willReturn($specNode);

        $this->beforeSpecification($event);

        $io->write("\n##teamcity[testSuiteStarted")->shouldHaveBeenCalled();
        $io->write(" name='spec\PhpSpec\Formatter\TeamCityFormatterSpec'")->shouldHaveBeenCalled();
        $io->write(
            " locationHint='php_qn://spec/PhpSpec/Formatter/TeamCityFormatterSpec.php::\\spec\PhpSpec\Formatter\TeamCityFormatterSpec'"
        )->shouldHaveBeenCalled();
    }

    function it_outputs_test_suite_started_for_after_specification(
        SpecificationEvent $event,
        ConsoleIO $io,
        SpecificationNode $specNode,
        Resource $resource
    ) {
        $resource->getSpecClassname()->willReturn('spec\PhpSpec\Formatter\TeamCityFormatterSpec');
        $specNode->getResource()->willReturn($resource);
        $event->getSpecification()->willReturn($specNode);

        $this->afterSpecification($event);

        $io->write("\n##teamcity[testSuiteFinished")->shouldHaveBeenCalled();
        $io->write(" name='spec\PhpSpec\Formatter\TeamCityFormatterSpec'")->shouldHaveBeenCalled();
    }

    function it_outputs_test_started_for_before_example(
        ExampleEvent $event,
        ConsoleIO $io,
        StatisticsCollector $stats,
        SpecificationNode $specNode,
        ReflectionClass $reflectionClass
    ) {
        $event->getTitle()->willReturn('it works nice before example');
        $event->getTime()->willReturn(0.1);
        $reflectionClass->getName()->willReturn('spec\PhpSpec\Formatter\TeamCityFormatterSpec');
        $reflectionClass->getFileName()->willReturn('spec/PhpSpec/Formatter/TeamCityFormatterSpec.php');
        $specNode->getClassReflection()->willReturn($reflectionClass);
        $event->getSpecification()->willReturn($specNode);
        $event->getResult()->willReturn(ExampleEvent::PASSED);
        $stats->getEventsCount()->willReturn(1);

        $this->beforeExample($event);

        $io->write("\n##teamcity[testStarted")->shouldHaveBeenCalled();
        $io->write(" name='it works nice before example'")->shouldHaveBeenCalled();
        $io->write(
            " locationHint='php_qn://spec/PhpSpec/Formatter/TeamCityFormatterSpec.php::\\spec\PhpSpec\Formatter\TeamCityFormatterSpec::it_works_nice_before_example'"
        )->shouldHaveBeenCalled();
    }

    function it_outputs_test_finished_for_a_passed_example(
        ExampleEvent $event,
        ConsoleIO $io,
        StatisticsCollector $stats
    ) {
        $event->getTitle()->willReturn('it works nice');
        $event->getTime()->willReturn(0.1);
        $event->getResult()->willReturn(ExampleEvent::PASSED);
        $stats->getEventsCount()->willReturn(1);

        $this->afterExample($event);

        $io->write("\n##teamcity[testFinished")->shouldHaveBeenCalled();
        $io->write(" name='it works nice'")->shouldHaveBeenCalled();
        $io->write(" duration='100'")->shouldHaveBeenCalled();
    }
}
