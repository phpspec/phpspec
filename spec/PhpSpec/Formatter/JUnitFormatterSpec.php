<?php

namespace spec\PhpSpec\Formatter;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use PhpSpec\Formatter\Presenter\PresenterInterface;
use PhpSpec\IO\IOInterface;
use PhpSpec\Listener\StatisticsCollector;
use PhpSpec\Event\SpecificationEvent;
use PhpSpec\Event\ExampleEvent;
use PhpSpec\Loader\Node\SpecificationNode;
use PhpSpec\Event\SuiteEvent;

class JUnitFormatterSpec extends ObjectBehavior
{
    function let(
        PresenterInterface $presenter,
        IOInterface $io,
        StatisticsCollector $stats
    ) {
        $this->beConstructedWith($presenter, $io, $stats);
    }

    function it_stores_a_testcase_node_after_passed_example_run(
        ExampleEvent $event,
        SpecificationNode $specification,
        \ReflectionClass $refClass
    ) {
        $event->getResult()->willReturn(ExampleEvent::PASSED);
        $event->getTitle()->willReturn('example title');
        $event->getTime()->willReturn(1337);
        $event->getSpecification()->willReturn($specification);
        $specification->getClassReflection()->willReturn($refClass);
        $refClass->getName()->willReturn('Acme\Foo\Bar');

        $this->afterExample($event);

        $this->getTestCaseNodes()->shouldReturn([
            '<testcase name="example title" time="1337" classname="Acme\Foo\Bar" status="passed" />'
        ]);
    }

    function it_stores_a_testcase_node_after_broken_example_run(
        ExampleEvent $event,
        SpecificationNode $specification,
        \ReflectionClass $refClass
    ) {
        $event->getResult()->willReturn(ExampleEvent::BROKEN);
        $event->getTitle()->willReturn('example title');
        $event->getTime()->willReturn(1337);

        $event->getException()->willReturn(new \RuntimeException('Something went wrong'));

        $event->getSpecification()->willReturn($specification);
        $specification->getClassReflection()->willReturn($refClass);
        $refClass->getName()->willReturn('Acme\Foo\Bar');

        $this->afterExample($event);

        $this->getTestCaseNodes()->shouldReturn([
            '<testcase name="example title" time="1337" classname="Acme\Foo\Bar" status="broken">' . "\n" .
                '<error type="RuntimeException" message="Something went wrong" />' . "\n" .
            '</testcase>'
        ]);
    }

    function it_aggregates_testcase_nodes_and_store_them_after_specification_run(SpecificationEvent $event)
    {
        $event->getTitle()->willReturn('specification title');

        $this->setTestCaseNodes(array(
            '<testcase name="example1" />',
            '<testcase name="example2" />',
            '<testcase name="example3" />',
        ));
        $this->afterSpecification($event);

        $this->getTestSuiteNodes()->shouldReturn([
            '<testsuite name="specification title" tests="3">' . "\n" .
                '<testcase name="example1" />' . "\n" .
                '<testcase name="example2" />' . "\n" .
                '<testcase name="example3" />' . "\n" .
            '</testsuite>'
        ]);
        $this->getTestCaseNodes()->shouldHaveCount(0);
    }

    function it_aggregates_testsuite_nodes_and_display_them_after_suite_run(SuiteEvent $event, $io)
    {
        $this->setTestSuiteNodes(array(
            '<testsuite name="specification1" tests="3">' . "\n" .
                '<testcase name="example1" />' . "\n" .
                '<testcase name="example2" />' . "\n" .
                '<testcase name="example3" />' . "\n" .
            '</testsuite>',
            '<testsuite name="specification2" tests="2">' . "\n" .
                '<testcase name="example1" />' . "\n" .
                '<testcase name="example2" />' . "\n" .
            '</testsuite>'
        ));
        $this->afterSuite($event);
        $io->write(
            '<testsuites>' . "\n" .
                '<testsuite name="specification1" tests="3">' . "\n" .
                    '<testcase name="example1" />' . "\n" .
                    '<testcase name="example2" />' . "\n" .
                    '<testcase name="example3" />' . "\n" .
                '</testsuite>' . "\n" .
                '<testsuite name="specification2" tests="2">' . "\n" .
                    '<testcase name="example1" />' . "\n" .
                    '<testcase name="example2" />' . "\n" .
                '</testsuite>' . "\n" .
            '</testsuites>'
        )->shouldBeCalled();
    }
}
