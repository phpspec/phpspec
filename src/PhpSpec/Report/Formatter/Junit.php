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
use PhpSpec\ProjectRoot;
use PhpSpec\Report\AbstractFormatter;
use PhpSpec\Result\ContextResult;
use PhpSpec\Result\ExampleResult;
use PhpSpec\Result\FeatureResult;
use PhpSpec\Result\MatchResult;
use PhpSpec\Result\ScenarioResult;
use PhpSpec\Result\StepResult;
use PhpSpec\Result\SuiteResult;
use PhpSpec\Results;

/**
 * @internal
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

        $xmlOutput = $xml->saveXML();
        if ($xmlOutput !== false) {
            $this->output->writeln($xmlOutput);
        }
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
        $time = 0.0;

        foreach ($examples as $example) {
            $testcase = $xml->createElement('testcase');
            $testcase->setAttribute('name', $example['title']);
            // The file's title, then the contexts, as the pretty sections name
            // an example: one title under two contexts reads apart.
            $testcase->setAttribute('classname', $example['context'] === '' ? $name : $name . ' > ' . $example['context']);
            $testcase->setAttribute('time', self::seconds($example['duration']));
            $time += $example['duration'];

            if ($example['pending'] || $example['skipped']) {
                $skip = $xml->createElement('skipped');
                if ($example['reason'] !== null) {
                    $skip->setAttribute('message', $example['reason']);
                }
                $testcase->appendChild($skip);
                $skipped++;
            } elseif ($example['error']) {
                $error = $xml->createElement('error');
                $error->setAttribute('message', $example['message']);
                $error->setAttribute('type', $example['type']);
                $error->appendChild($xml->createTextNode(trim($example['message'] . "\n\n" . $example['at'])));
                $testcase->appendChild($error);
                $errors++;
            } elseif ($example['failure']) {
                $failure = $xml->createElement('failure');
                $failure->setAttribute('message', $example['message']);
                $failure->appendChild($xml->createTextNode(trim($example['message'] . "\n\n" . $example['at'])));
                $testcase->appendChild($failure);
                $failures++;
            }

            $suite->appendChild($testcase);
        }

        $suite->setAttribute('failures', (string) $failures);
        $suite->setAttribute('errors', (string) $errors);
        $suite->setAttribute('skipped', (string) $skipped);
        $suite->setAttribute('time', self::seconds($time));
        $parent->appendChild($suite);
    }

    /**
     * Where an example failed or errored, from the project root, as PHPUnit
     * writes it under the message; empty when that is not known.
     */
    private function whereItFailed(ExampleResult $example): string
    {
        $failed = array_values(array_filter($example->getResults(), static fn(MatchResult $match): bool => $match->isFailure()))[0] ?? null;
        $file = $failed?->getFile() ?? $example->getError()?->getFile();
        $line = $failed?->getLine() ?? $example->getError()?->getLine();

        return $file === null || $file === '' || $line === null ? '' : ProjectRoot::here()->relative($file) . ':' . $line;
    }

    /**
     * A duration as JUnit's time attribute: seconds, to the microsecond.
     */
    private static function seconds(float $duration): string
    {
        return sprintf('%.6F', $duration);
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

        // Where each scenario lives travels with the report, so a reader of it
        // can say how to re-run one.
        if ($feature->getPath() !== '') {
            $featureSuite->setAttribute('file', $feature->getPath());
        }

        $featureTime = 0.0;
        $featureCounts = ['tests' => 0, 'failures' => 0, 'errors' => 0, 'skipped' => 0];

        foreach ($feature->getResults() as $scenario) {
            if (!$scenario instanceof ScenarioResult) {
                continue;
            }
            $scenarioSuite = $xml->createElement('testsuite');
            $scenarioSuite->setAttribute('name', $scenario->getTitle());
            $scenarioSuite->setAttribute('type', 'scenario');

            if ($scenario->getLine() > 0) {
                $scenarioSuite->setAttribute('line', (string) $scenario->getLine());
            }

            $scenarioTime = 0.0;
            $counts = ['tests' => 0, 'failures' => 0, 'errors' => 0, 'skipped' => 0];

            foreach ($scenario->getResults() as $step) {
                if (!$step instanceof StepResult) {
                    continue;
                }
                $counts['tests']++;
                $testcase = $xml->createElement('testcase');
                $testcase->setAttribute('name', $step->getTitle());
                $testcase->setAttribute('classname', $feature->getTitle() . ' > ' . $scenario->getTitle());
                $testcase->setAttribute('time', self::seconds($step->getDuration()));
                $scenarioTime += $step->getDuration();

                if ($step->isUndefined()) {
                    // An undefined step fails the run, so it fails the report.
                    $failure = $xml->createElement('failure');
                    $failure->setAttribute('message', 'Undefined step: ' . $step->getTitle());
                    $testcase->appendChild($failure);
                    $counts['failures']++;
                } elseif ($step->isPending() || $step->isSkipped()) {
                    $skip = $xml->createElement('skipped');
                    if ($step->getReason() !== null) {
                        $skip->setAttribute('message', $step->getReason());
                    }
                    $testcase->appendChild($skip);
                    $counts['skipped']++;
                } elseif ($step->isError()) {
                    $error = $xml->createElement('error');
                    $error->setAttribute('message', $step->getError()?->getMessage() ?? 'Errored');
                    $error->setAttribute('type', $step->getError()?->getType() ?? 'Error');
                    $testcase->appendChild($error);
                    $counts['errors']++;
                } elseif ($step->isFailure()) {
                    $failure = $xml->createElement('failure');
                    $failure->setAttribute('message', $step->getError()?->getMessage() ?? 'Failed');
                    $testcase->appendChild($failure);
                    $counts['failures']++;
                }

                $scenarioSuite->appendChild($testcase);
            }

            foreach ($counts as $count => $value) {
                $scenarioSuite->setAttribute($count, (string) $value);
                $featureCounts[$count] += $value;
            }

            $scenarioSuite->setAttribute('time', self::seconds($scenarioTime));
            $featureTime += $scenarioTime;
            $featureSuite->appendChild($scenarioSuite);
        }

        foreach ($featureCounts as $count => $value) {
            $featureSuite->setAttribute($count, (string) $value);
        }

        $featureSuite->setAttribute('time', self::seconds($featureTime));
        $parent->appendChild($featureSuite);
    }

    /**
     * Recursively collects all examples into a flat list for testsuite construction.
     *
     * @param array<int, array{title: string, context: string, duration: float, pending: bool, skipped: bool, reason: string|null, error: bool, failure: bool, message: string, type: string, at: string}> $examples
     * @return array<int, array{title: string, context: string, duration: float, pending: bool, skipped: bool, reason: string|null, error: bool, failure: bool, message: string, type: string, at: string}>
     */
    private function collectExamples(Results $results, string $prefix = '', array &$examples = []): array
    {
        foreach ($results->getResults() as $result) {
            if ($result instanceof ExampleResult) {
                $examples[] = [
                    'title' => $result->getTitle(),
                    'context' => $prefix,
                    'duration' => $result->getDuration(),
                    'pending' => $result->isPending(),
                    'skipped' => $result->isSkipped(),
                    'reason' => $result->getReason(),
                    'error' => $result->isError(),
                    'failure' => $result->isFailure(),
                    'message' => $result->getMessage(),
                    'type' => $result->getError()?->getType() ?? 'Error',
                    'at' => $this->whereItFailed($result),
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
