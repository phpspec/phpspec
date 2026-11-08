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

use PhpSpec\Report\AbstractFormatter;
use PhpSpec\Result\Outcome;
use PhpSpec\Result\Outcomes;
use PhpSpec\Result\State;
use PhpSpec\Result\SuiteResult;
use PhpSpec\Results;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 * Renders results in TAP (Test Anything Protocol) version 13 format: one test
 * point per example and per scenario, ok or not ok as the run judges it, with
 * YAML diagnostics for one that fails the run.
 */
final class Tap extends AbstractFormatter
{
    private int $testNumber = 0;

    public function __construct(
        OutputInterface $output,
        private readonly Outcomes $outcomes,
    ) {
        parent::__construct($output);
    }

    /**
     * Batch format keeps the leading plan line (1..N) for backwards compatibility.
     */
    public function format(SuiteResult $results): void
    {
        $outcomes = [];
        $this->outcomes->walk($results, function (Outcome $outcome) use (&$outcomes): void {
            $outcomes[] = $outcome;
        });

        $this->output->writeln('TAP version 13');
        $this->output->writeln('1..' . count($outcomes));

        foreach ($outcomes as $i => $outcome) {
            $this->testPoint($i + 1, $outcome);
        }
    }

    /**
     * Outputs the TAP version header and resets the test counter.
     */
    public function begin(): void
    {
        $this->output->writeln('TAP version 13');
        $this->testNumber = 0;
    }

    /**
     * Outputs a test point for each example and scenario in the result tree.
     */
    public function printResult(Results $result): void
    {
        $this->outcomes->walk($result, function (Outcome $outcome): void {
            $this->testPoint(++$this->testNumber, $outcome);
        });
    }

    /**
     * Outputs the TAP plan line with the total test count.
     */
    public function end(SuiteResult $results): void
    {
        $this->output->writeln("1..{$this->testNumber}");
    }

    /**
     * One test point: ok, ok with a SKIP directive and its reason for work
     * left pending or skipped, or not ok with what went wrong, the step that
     * decided a scenario and where it went wrong.
     */
    private function testPoint(int $number, Outcome $outcome): void
    {
        $name = $outcome->name();

        if ($outcome->state === State::Pending || $outcome->state === State::Skipped) {
            $this->output->writeln("ok {$number} - {$name} # SKIP " . ($outcome->reason ?? $outcome->state->value));

            return;
        }

        if (!$outcome->state->failsTheRun()) {
            $this->output->writeln("ok {$number} - {$name}");

            return;
        }

        $this->output->writeln("not ok {$number} - {$name}");
        $this->output->writeln('  ---');
        $this->output->writeln('  message: ' . $this->yamlEscape($outcome->message ?? ''));
        if ($outcome->step !== null) {
            $this->output->writeln('  step: ' . $this->yamlEscape($outcome->step));
        }
        if ($outcome->failedAt !== null) {
            $this->output->writeln('  at: ' . $this->yamlEscape((string) $outcome->failedAt));
        }
        $this->output->writeln('  ...');
    }

    /**
     * Escapes a string for safe inclusion in YAML diagnostic output.
     * Quotes the value when it contains characters that are special in YAML:
     * colons, hashes, brackets, braces, quotes, newlines, tabs, etc.
     */
    private function yamlEscape(string $value): string
    {
        // Characters that require double-quoting in YAML
        if (preg_match('/[\n\r\t\\\\"\\x00-\\x1f]|^[\'"\-?:{}[\]|>&!%@`#,]|: |^ | $/', $value)) {
            return '"' . addcslashes($value, "\"\\\n\r\t") . '"';
        }

        return $value;
    }
}
