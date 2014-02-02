<?php

/*
 * This file is part of PhpSpec, A php toolset to drive emergent
 * design by specification.
 *
 * (c) Marcello Duarte <marcello.duarte@gmail.com>
 * (c) Konstantin Kudryashov <ever.zet@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpSpec\Formatter;

use PhpSpec\IO\IOInterface as IO;
use PhpSpec\Formatter\Presenter\PresenterInterface;
use PhpSpec\Listener\StatisticsCollector;

use PhpSpec\Event\ExampleEvent;
use PhpSpec\Event\SuiteEvent;
use PhpSpec\Event\SpecificationEvent;

/**
 * The JUnit Formatter
 *
 * @author Gildas Quemener <gildas.quemener@gmail.com>
 */
class JUnitFormatter extends BasicFormatter
{
    /** @var array */
    protected $testCaseNodes = array();

    /** @var array */
    protected $testSuiteNodes = array();

    /** @var array */
    protected $exampleStatusCounts = array();

    /** @var array */
    protected $jUnitStatuses = array(
        ExampleEvent::PASSED  => 'passed',
        ExampleEvent::PENDING => 'pending',
        ExampleEvent::FAILED  => 'failed',
        ExampleEvent::BROKEN  => 'broken',
    );

    /** @var array */
    protected $resultTags = array(
        ExampleEvent::FAILED  => 'failure',
        ExampleEvent::BROKEN  => 'error',
    );

    public function __construct(PresenterInterface $presenter, IO $io, StatisticsCollector $stats)
    {
        parent::__construct($presenter, $io, $stats);

        $this->initTestCaseNodes();
    }

    /**
     * Set testcase nodes
     *
     * @param array $testCaseNodes
     */
    public function setTestCaseNodes(array $testCaseNodes)
    {
        $this->testCaseNodes = $testCaseNodes;
    }

    /**
     * Get testcase nodes
     *
     * @return array
     */
    public function getTestCaseNodes()
    {
        return $this->testCaseNodes;
    }

    /**
     * Set testsuite nodes
     *
     * @param array $testSuiteNodes
     */
    public function setTestSuiteNodes(array $testSuiteNodes)
    {
        $this->testSuiteNodes = $testSuiteNodes;
    }

    /**
     * Get testsuite nodes
     *
     * @return array
     */
    public function getTestSuiteNodes()
    {
        return $this->testSuiteNodes;
    }

    public function setExampleStatusCounts(array $exampleStatusCounts)
    {
        $this->exampleStatusCounts = $exampleStatusCounts;
    }

    public function getExampleStatusCounts()
    {
        return $this->exampleStatusCounts;
    }

    /**
     * {@inheritdoc}
     */
    public function afterExample(ExampleEvent $event)
    {
        $testCaseNode = sprintf(
            '<testcase name="%s" time="%s" classname="%s" status="%s"',
            $event->getTitle(),
            $event->getTime(),
            $event->getSpecification()->getClassReflection()->getName(),
            $this->jUnitStatuses[$event->getResult()]
        );

        $this->exampleStatusCounts[$event->getResult()]++;

        if (in_array($event->getResult(), array(ExampleEvent::BROKEN, ExampleEvent::FAILED))) {
            $exception = $event->getException();
            $testCaseNode .= sprintf(
                '>' . "\n" .
                    '<%s type="%s" message="%s" />' . "\n" .
                    '<system-err>' . "\n" .
                        '<![CDATA[' . "\n" .
                            '%s' . "\n" .
                        ']]>' . "\n" .
                    '</system-err>' . "\n" .
                '</testcase>',
                $this->resultTags[$event->getResult()],
                get_class($exception),
                htmlentities($exception->getMessage()),
                $exception->getTraceAsString()
            );
        } else {
            $testCaseNode .= ' />';
        }

        $this->testCaseNodes[] = $testCaseNode;
    }

    /**
     * {@inheritdoc}
     */
    public function afterSpecification(SpecificationEvent $event)
    {
        $this->testSuiteNodes[] = sprintf(
            '<testsuite name="%s" time="%s" tests="%s" failures="%s" errors="%s" skipped="%s">' . "\n" .
                '%s' . "\n" .
            '</testsuite>',
            $event->getTitle(),
            $event->getTime(),
            count($this->testCaseNodes),
            $this->exampleStatusCounts[ExampleEvent::FAILED],
            $this->exampleStatusCounts[ExampleEvent::BROKEN],
            $this->exampleStatusCounts[ExampleEvent::PENDING],
            implode("\n", $this->testCaseNodes)
        );

        $this->initTestCaseNodes();
    }

    /**
     * {@inheritdoc}
     */
    public function afterSuite(SuiteEvent $event)
    {
        $this->getIo()->write(sprintf(
            '<testsuites>' . "\n" .
            '%s' . "\n" .
            '</testsuites>',
            implode("\n", $this->testSuiteNodes)
        ));
    }

    /**
     * Initialize test case nodes and example status counts
     */
    protected function initTestCaseNodes()
    {
        $this->testCaseNodes       = array();
        $this->exampleStatusCounts = array(
            ExampleEvent::PASSED  => 0,
            ExampleEvent::PENDING => 0,
            ExampleEvent::FAILED  => 0,
            ExampleEvent::BROKEN  => 0,
        );
    }
}
