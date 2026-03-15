<?php

/*
 * This file is part of PhpSpec, A php toolset to drive emergent
 * design by specification.
 *
 * (c) Marcello Duarte <marcello.duarte@gmail.com>
 * (c) Konstantin Kudryashov <ever.zet@gmail.com>
 * (c) Ciaran McNulty <ciaran@ciaranmcnulty.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpSpec\Report\Formatter;

use DOMDocument;
use DOMElement;
use DOMException;
use PhpSpec\Report\AbstractFormatter;
use PhpSpec\Result\ContextResult;
use PhpSpec\Result\ExampleResult;
use PhpSpec\Result\FeatureResult;
use PhpSpec\Result\ScenarioResult;
use PhpSpec\Result\StepResult;
use PhpSpec\Result\SuiteResult;
use PhpSpec\Results;

/**
 * Renders spec results as JUnit XML, compatible with CI tools that consume JUnit reports.
 * Produces a testsuites document with nested testsuite and testcase elements.
 */
final class Junit extends AbstractFormatter
{
    /**
     * No-op; JUnit XML is non-streamable, all output happens in end().
     */
    public function begin(): void
    {
        // XML is non-streamable; all output happens in end()
    }

    /**
     * No-op; JUnit XML is non-streamable, all output happens in end().
     */
    public function printResult(Results $result): void
    {
        // XML is non-streamable; all output happens in end()
    }

    /**
     * Builds and outputs the complete JUnit XML document from all collected results.
     */
    public function end(SuiteResult $results): void
    {
        $this->writeXml($results);
    }

    /**
     * Builds and writes the complete JUnit XML document.
     *
     * @throws DOMException
     */
    private function writeXml(SuiteResult $results): void
    {
        $xml = new DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;

        $testSuites = $xml->createElement('testsuites');
        $xml->appendChild($testSuites);

        foreach ($results->getResults() as $specResult) {
            $this->buildTestSuite($xml, $testSuites, $specResult);
        }

        $this->output->writeln($xml->saveXML());
    }

    /**
     * Builds a testsuite XML element with testcase children for the given results.
     *
     * @throws DOMException
     */
    private function buildTestSuite(DOMDocument $xml, DOMElement $parent, Results $results, string $prefix = ''): void
    {
        if ($results instanceof FeatureResult) {
            $this->buildFeatureSuite($xml, $parent, $results);
            return;
        }

        $examples = $this->collectExamples($results);

        $suite = $xml->createElement('testsuite');
        $name = $prefix;
        if (method_exists($results, 'getTitle')) {
            $name = $prefix ? "{$prefix} > {$results->getTitle()}" : $results->getTitle();
        }
        $suite->setAttribute('name', $name);
        $suite->setAttribute('tests', (string) count($examples));

        $failures = 0;
        $errors = 0;
        $skipped = 0;

        foreach ($examples as $example) {
            $testcase = $xml->createElement('testcase');
            $testcase->setAttribute('name', $example['title']);
            $testcase->setAttribute('classname', $name);

            if ($example['pending']) {
                $skip = $xml->createElement('skipped');
                $testcase->appendChild($skip);
                $skipped++;
            } elseif ($example['error']) {
                $error = $xml->createElement('error');
                $error->setAttribute('message', $example['message']);
                $testcase->appendChild($error);
                $errors++;
            } elseif ($example['failure']) {
                $failure = $xml->createElement('failure');
                $failure->setAttribute('message', $example['message']);
                $testcase->appendChild($failure);
                $failures++;
            }

            $suite->appendChild($testcase);
        }

        $suite->setAttribute('failures', (string) $failures);
        $suite->setAttribute('errors', (string) $errors);
        $suite->setAttribute('skipped', (string) $skipped);
        $parent->appendChild($suite);
    }

    /**
     * Builds nested testsuites for a feature: feature > scenario > step testcases.
     *
     * @throws DOMException
     */
    private function buildFeatureSuite(DOMDocument $xml, DOMElement $parent, FeatureResult $feature): void
    {
        $featureSuite = $xml->createElement('testsuite');
        $featureSuite->setAttribute('name', $feature->getTitle());
        $featureSuite->setAttribute('type', 'feature');

        foreach ($feature->getResults() as $scenario) {
            if (!$scenario instanceof ScenarioResult) {
                continue;
            }
            $scenarioSuite = $xml->createElement('testsuite');
            $scenarioSuite->setAttribute('name', $scenario->getTitle());
            $scenarioSuite->setAttribute('type', 'scenario');

            foreach ($scenario->getResults() as $step) {
                if (!$step instanceof StepResult) {
                    continue;
                }
                $testcase = $xml->createElement('testcase');
                $testcase->setAttribute('name', $step->getTitle());
                $testcase->setAttribute('classname', $feature->getTitle());

                if ($step->isPending() || $step->isUndefined() || $step->isSkipped()) {
                    $testcase->appendChild($xml->createElement('skipped'));
                } elseif ($step->isFailure()) {
                    $failure = $xml->createElement('failure');
                    $failure->setAttribute('message', $step->getError()?->getMessage() ?? 'Failed');
                    $testcase->appendChild($failure);
                }

                $scenarioSuite->appendChild($testcase);
            }

            $featureSuite->appendChild($scenarioSuite);
        }

        $parent->appendChild($featureSuite);
    }

    /**
     * Recursively collects all examples into a flat list for testsuite construction.
     *
     * @return array<int, array{title: string, pending: bool, error: bool, failure: bool, message: string}>
     */
    private function collectExamples(Results $results, string $prefix = '', array &$examples = []): array
    {
        foreach ($results->getResults() as $result) {
            if ($result instanceof ExampleResult) {
                $examples[] = [
                    'title' => $result->getTitle(),
                    'pending' => $result->isPending(),
                    'error' => $result->isError(),
                    'failure' => $result->isFailure(),
                    'message' => $result->getMessage(),
                ];
            } elseif ($result instanceof Results) {
                $newPrefix = $prefix;
                if ($result instanceof ContextResult) {
                    $newPrefix = $prefix ? "{$prefix} > {$result->getTitle()}" : $result->getTitle();
                }
                $this->collectExamples($result, $newPrefix, $examples);
            }
        }

        return $examples;
    }
}
