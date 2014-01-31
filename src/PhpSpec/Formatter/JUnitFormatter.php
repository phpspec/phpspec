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

    /**
     * {@inheritdoc}
     */
    public function afterExample(ExampleEvent $event)
    {
        $this->testCaseNodes[] = sprintf('<testcase name="%s" />', $event->getTitle());
    }

    /**
     * {@inheritdoc}
     */
    public function afterSpecification(SpecificationEvent $event)
    {
        $this->testSuiteNodes[] = sprintf(
            '<testsuite name="%s" tests="%s">' . "\n" .
            '%s' . "\n" .
            '</testsuite>',
            $event->getTitle(),
            count($this->testCaseNodes),
            implode("\n", $this->testCaseNodes)
        );

        $this->testCaseNodes = array();
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
}
