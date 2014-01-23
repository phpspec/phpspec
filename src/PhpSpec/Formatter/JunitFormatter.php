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
 * @author Nick Peirson <nickpeirson@gmail.com>
 */
class JunitFormatter extends BasicFormatter
{
    /**
     * @var
     */
    private $xml;
    /**
     * @var
     */
    private $testSuite;
    /**
     * @var
     */
    private $currentGroup;
    /**
     * @var
     */
    private $currentFile;
    /**
     * @var int
     */
    private $suiteTime = 0;
    /**
     * @var int
     */
    private $assertionCount = 0;
    /**
     * @var int
     */
    private $passCount = 0;
    /**
     * @var int
     */
    private $pendingCount = 0;
    /**
     * @var int
     */
    private $failCount = 0;
    /**
     * @var int
     */
    private $brokenCount = 0;

    /**
     * @param SuiteEvent $event
     */
    public function beforeSuite(SuiteEvent $event)
    {
        $this->xml = new \SimpleXMLElement("<testsuites></testsuites>");
    }

    /**
     * @param SpecificationEvent $event
     */
    public function beforeSpecification(SpecificationEvent $event)
    {
        $this->currentGroup = $event->getTitle();
        $this->currentFile = $event->getSpecification()->getClassReflection()->getFileName();

        $this->testSuite = $this->xml->addChild('testsuite');
        $this->testSuite->addAttribute('name', $this->currentGroup);
        $this->testSuite->addAttribute('file', $this->currentFile);

        $this->suiteTime = 0;
        $this->assertionCount = 0;
        $this->passCount = 0;
        $this->pendingCount = 0;
        $this->failCount = 0;
        $this->brokenCount = 0;
    }

    /**
     * @param \SimpleXMLElement $case
     * @param ExampleEvent      $event
     * @param string            $exampleTitle
     * @param string            $failureType
     * @param string            $failureString
     * @param bool              $backtrace
     */
    private function addFailedTestcase(\SimpleXMLElement $case, ExampleEvent $event, $exampleTitle, $failureType, $failureString, $backtrace = true)
    {
        $failureMsg = PHP_EOL . $exampleTitle
            . ' ('.$failureString.')' . PHP_EOL;
        $failureMsg .= $event->getMessage() . PHP_EOL;
        if ($backtrace) {
            $failureMsg .= $event->getException()->getTraceAsString() . PHP_EOL;
        }

        $error = $case->addChild($failureType, $failureMsg);
        $error->addAttribute(
            'type',
            get_class($event->getException())
        );
    }

    /**
     * @param ExampleEvent $event
     */
    public function afterExample(ExampleEvent $event)
    {
        $title = preg_replace('/^it /', '', $event->getTitle());
        $line  = $event->getExample()->getFunctionReflection()->getStartLine();
        $time = $event->getTime();
        $assertions = 0;

        $case = $this->testSuite->addChild('testcase');
        $case->addAttribute('name', $title);
        $case->addAttribute('class', $this->currentGroup);
        $case->addAttribute('file', $this->currentFile);
        $case->addAttribute('line', $line);
        $case->addAttribute('assertions', $assertions);
        $case->addAttribute('time', $time);

        switch ($event->getResult()) {
            case ExampleEvent::PASSED:
                $this->passCount++;
                break;
            case ExampleEvent::PENDING:
                $this->addFailedTestcase($case, $event, $title, 'failure', 'PENDING', false);
                $this->pendingCount++;
                break;
            case ExampleEvent::FAILED:
                $this->addFailedTestcase($case, $event, $title, 'failure', 'FAILED');
                $this->failCount++;
                break;
            case ExampleEvent::BROKEN:
                $this->addFailedTestcase($case, $event, $title, 'error', 'ERROR');
                $this->brokenCount++;
                break;
        }

        $this->assertionCount += $assertions;
        $this->suiteTime += $time;
    }

    /**
     * @param SpecificationEvent $event
     */
    public function afterSpecification(SpecificationEvent $event)
    {
        $this->testSuite->addAttribute('tests', $event->getSpecification()->count());
        $this->testSuite->addAttribute('assertions', $this->assertionCount);
        $this->testSuite->addAttribute('failures', $this->failCount);
        $this->testSuite->addAttribute('errors', $this->brokenCount);
        $this->testSuite->addAttribute('time', $this->suiteTime);
    }

    /**
     * @param SuiteEvent $event
     */
    public function afterSuite(SuiteEvent $event)
    {
        $dom = new \DOMDocument('1.0');
        $dom->preserveWhitespace = false;
        $dom->formatOutput = true;
        $dom->loadXml($this->xml->asXml());
        $this->getIO()->write($dom->saveXML());
    }
}
