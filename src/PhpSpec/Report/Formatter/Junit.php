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
use PhpSpec\Result\FeatureResult;
use PhpSpec\Result\Outcome;
use PhpSpec\Result\Outcomes;
use PhpSpec\Result\State;
use PhpSpec\Result\SuiteResult;
use PhpSpec\Results;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 * Renders results as JUnit XML, compatible with CI tools that consume JUnit
 * reports: a testsuite for each spec file and each feature, a testcase for
 * each example and each scenario, judged as the run judges it.
 */
final class Junit extends AbstractFormatter
{
    /** What each verdict element counts towards on its testsuite. */
    private const COUNTED_AS = ['skipped' => 'skipped', 'error' => 'errors', 'failure' => 'failures'];

    public function __construct(
        OutputInterface $output,
        private readonly Outcomes $outcomes,
    ) {
        parent::__construct($output);
    }

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

        foreach ($results->getResults() as $result) {
            $this->testSuite($xml, $testSuites, $result);
        }

        $xmlOutput = $xml->saveXML();
        if ($xmlOutput !== false) {
            $this->output->writeln($xmlOutput);
        }
    }

    /**
     * A testsuite for a spec file or a feature, with a testcase for each
     * example or scenario in it. A spec file's cases are classed under the
     * file, then their describe and contexts, as PHPUnit classes a test under
     * its class; a feature's under the feature.
     *
     * @throws DOMException
     */
    private function testSuite(DOMDocument $xml, DOMElement $parent, Results $result): void
    {
        $title = method_exists($result, 'getTitle') ? (string) $result->getTitle() : '';
        $suite = $xml->createElement('testsuite');
        $suite->setAttribute('name', $title);
        $classedUnder = [$title];

        if ($result instanceof FeatureResult) {
            $suite->setAttribute('type', 'feature');
            if ($result->getPath() !== '') {
                $suite->setAttribute('file', $result->getPath());
            }
            $classedUnder = [];
        }

        $counts = ['tests' => 0, 'failures' => 0, 'errors' => 0, 'skipped' => 0];
        $time = 0.0;

        $this->outcomes->walk($result, function (Outcome $outcome) use ($xml, $suite, $classedUnder, &$counts, &$time): void {
            $suite->appendChild($this->testCase($xml, $outcome, $classedUnder, $counts));
            $time += $outcome->duration;
        });

        foreach ($counts as $count => $value) {
            $suite->setAttribute($count, (string) $value);
        }
        $suite->setAttribute('time', self::seconds($time));
        $parent->appendChild($suite);
    }

    /**
     * @param list<string> $classedUnder
     * @param array{tests: int, failures: int, errors: int, skipped: int} $counts
     *
     * @throws DOMException
     */
    private function testCase(DOMDocument $xml, Outcome $outcome, array $classedUnder, array &$counts): DOMElement
    {
        $counts['tests']++;
        $testcase = $xml->createElement('testcase');
        $testcase->setAttribute('name', $outcome->title());
        $testcase->setAttribute('classname', implode(' > ', [...$classedUnder, ...array_slice($outcome->titles, 0, -1)]));
        if ($outcome->writtenAt !== null) {
            $testcase->setAttribute('file', $outcome->writtenAt->file);
            $testcase->setAttribute('line', (string) $outcome->writtenAt->line);
        }
        $testcase->setAttribute('time', self::seconds($outcome->duration));

        $element = match ($outcome->state) {
            State::Pending, State::Skipped => 'skipped',
            State::Errored => 'error',
            State::Failed, State::Undefined => 'failure',
            State::Passed, State::Risky => null,
        };

        if ($element !== null) {
            $counts[self::COUNTED_AS[$element]]++;
            $testcase->appendChild($this->verdict($xml, $element, $outcome));
        }

        return $testcase;
    }

    /**
     * The verdict a testcase carries: skipped with the reason given, or what
     * went wrong and where, written under the message as PHPUnit writes it.
     *
     * @throws DOMException
     */
    private function verdict(DOMDocument $xml, string $element, Outcome $outcome): DOMElement
    {
        $verdict = $xml->createElement($element);

        if ($element === 'skipped') {
            if ($outcome->reason !== null) {
                $verdict->setAttribute('message', $outcome->reason);
            }

            return $verdict;
        }

        $verdict->setAttribute('message', $outcome->message ?? '');
        if ($element === 'error') {
            $verdict->setAttribute('type', $outcome->errorType ?? 'Error');
        }
        $verdict->appendChild($xml->createTextNode(trim(($outcome->message ?? '') . "\n\n" . $outcome->failedAt)));

        return $verdict;
    }

    /**
     * A duration as JUnit's time attribute: seconds, to the microsecond.
     */
    private static function seconds(float $duration): string
    {
        return sprintf('%.6F', $duration);
    }
}
