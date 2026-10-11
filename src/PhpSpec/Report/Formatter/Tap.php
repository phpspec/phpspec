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
use PhpSpec\Result\ContextResult;
use PhpSpec\Result\ExampleResult;
use PhpSpec\Result\ScenarioResult;
use PhpSpec\Result\StepResult;
use PhpSpec\Result\SuiteResult;
use PhpSpec\Results;

/**
 * @internal
 * Renders results in TAP (Test Anything Protocol) version 13 format: one
 * line per example and per scenario, ok or not ok, with YAML diagnostics for
 * a failure.
 */
final class Tap extends AbstractFormatter
{
    private int $testNumber = 0;

    /**
     * Batch format keeps the leading plan line (1..N) for backwards compatibility.
     */
    public function format(SuiteResult $results): void
    {
        $examples = $this->collectExamples($results);
        $total = count($examples);

        $this->output->writeln('TAP version 13');
        $this->output->writeln("1..{$total}");

        foreach ($examples as $i => $example) {
            $num = $i + 1;
            $this->outputExample($num, $example);
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
     * Outputs ok/not ok lines for each example in the result tree.
     */
    public function printResult(Results $result): void
    {
        $examples = $this->collectExamples($result);
        foreach ($examples as $example) {
            $this->testNumber++;
            $this->outputExample($this->testNumber, $example);
        }
    }

    /**
     * Outputs the TAP plan line with the total test count.
     */
    public function end(SuiteResult $results): void
    {
        $this->output->writeln("1..{$this->testNumber}");
    }

    /**
     * Outputs a single TAP test line with optional YAML diagnostics.
     *
     * @param int $num the test number
     * @param array{title: string, pending: bool, skipped: bool, reason: string|null, error: bool, failure: bool, message: string} $example
     */
    private function outputExample(int $num, array $example): void
    {
        $title = $example['title'];

        if ($example['pending'] || $example['skipped']) {
            $why = $example['reason'] ?? ($example['pending'] ? 'pending' : 'skipped');
            $this->output->writeln("ok {$num} - {$title} # SKIP {$why}");
        } elseif ($example['error'] || $example['failure']) {
            $this->output->writeln("not ok {$num} - {$title}");
            $this->output->writeln('  ---');
            $this->output->writeln('  message: ' . $this->yamlEscape($example['message']));
            $this->output->writeln('  ...');
        } else {
            $this->output->writeln("ok {$num} - {$title}");
        }
    }

    /**
     * Recursively collects all examples into a flat list with context-prefixed titles.
     *
     * @param array<int, array{title: string, pending: bool, skipped: bool, reason: string|null, error: bool, failure: bool, message: string}> $examples
     * @return array<int, array{title: string, pending: bool, skipped: bool, reason: string|null, error: bool, failure: bool, message: string}>
     */
    private function collectExamples(Results $results, string $prefix = '', array &$examples = []): array
    {
        foreach ($results->getResults() as $result) {
            if ($result instanceof ExampleResult) {
                $examples[] = [
                    'title' => $prefix ? "{$prefix} {$result->getTitle()}" : $result->getTitle(),
                    'pending' => $result->isPending(),
                    'skipped' => $result->isSkipped(),
                    'reason' => $result->getReason(),
                    'error' => $result->isError(),
                    'failure' => $result->isFailure(),
                    'message' => $result->getMessage(),
                ];
            } elseif ($result instanceof ScenarioResult) {
                $examples[] = $this->scenario($result, $prefix);
            } elseif ($result instanceof Results) {
                $newPrefix = $prefix;
                if ($result instanceof ContextResult) {
                    $newPrefix = $prefix ? "{$prefix} {$result->getTitle()}" : $result->getTitle();
                }
                $this->collectExamples($result, $newPrefix, $examples);
            }
        }

        return $examples;
    }

    /**
     * A scenario as one test point, judged as the run judges it: the first
     * step that failed, errored or is undefined fails it, a pending one
     * leaves it to do.
     *
     * @return array{title: string, pending: bool, skipped: bool, reason: string|null, error: bool, failure: bool, message: string}
     */
    private function scenario(ScenarioResult $scenario, string $prefix): array
    {
        $point = [
            'title' => $prefix ? "{$prefix} {$scenario->getTitle()}" : $scenario->getTitle(),
            'pending' => false,
            'skipped' => false,
            'reason' => null,
            'error' => false,
            'failure' => false,
            'message' => '',
        ];

        foreach ($scenario->getResults() as $step) {
            if (!$step instanceof StepResult) {
                continue;
            }

            if ($step->isError() || $step->isFailure()) {
                $said = $step->getError()?->getMessage() ?? $step->getMatch()?->getMessage() ?? '';

                return ['error' => $step->isError(), 'failure' => !$step->isError(), 'message' => trim($step->getTitle() . ': ' . $said, ': ')] + $point;
            }

            if ($step->isUndefined()) {
                return ['failure' => true, 'message' => 'Undefined step: ' . $step->getTitle()] + $point;
            }

            if ($step->isPending() && !$point['pending']) {
                $point = ['pending' => true, 'reason' => $step->getReason()] + $point;
            }
        }

        return $point;
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
