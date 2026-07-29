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

use PhpSpec\Report\Formatter\Pretty\PrettyViews;
use PhpSpec\Result\ContextResult;
use PhpSpec\Result\ExampleResult;
use PhpSpec\Result\FeatureResult;
use PhpSpec\Result\MatchResult;
use PhpSpec\Result\ScenarioResult;
use PhpSpec\Result\SpecificationResult;
use PhpSpec\Result\StepResult;
use PhpSpec\Result\SuiteResult;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 * The end-of-run detail, grouped by kind: Failures, Errors, Warnings,
 * Deprecations, and Skipped, each section printed only when it has entries.
 * Shared by the pretty and dot formatters so both tell the same story. A
 * failure whose matcher has a relation reads as a labeled pair (expected /
 * to be contained in) instead of a sentence embedding the values.
 */
final class DetailSections
{
    /** @var array<string, list<callable(OutputInterface): void>> */
    private array $sections = [];

    /**
     * Renders every non-empty section, in severity order.
     */
    public function render(OutputInterface $output, SuiteResult $results): void
    {
        $this->sections = ['Failures' => [], 'Errors' => [], 'Warnings' => [], 'Deprecations' => [], 'Skipped' => []];

        foreach ($results->getResults() as $node) {
            if ($node instanceof FeatureResult) {
                $this->collectFeature($node);
            } elseif ($node instanceof SpecificationResult) {
                $this->collectChildren($node->getResults(), $node->getTitle());
            }
        }

        $colours = ['Failures' => 'red', 'Errors' => 'red', 'Warnings' => 'yellow', 'Deprecations' => 'yellow', 'Skipped' => 'cyan'];
        foreach ($this->sections as $name => $entries) {
            if ($entries === []) {
                continue;
            }

            $output->write(PHP_EOL . '  <fg=' . $colours[$name] . ';options=bold>' . $name . ':</>' . PHP_EOL);
            foreach ($entries as $entry) {
                $entry($output);
            }
        }
    }

    /**
     * Walks a specification subtree, threading the title path down.
     *
     * @param array<int, mixed> $children
     */
    private function collectChildren(array $children, string $path): void
    {
        foreach ($children as $child) {
            if ($child instanceof ContextResult) {
                if ($child->isError() && $child->getError() !== null) {
                    $this->collectContextError($child, $path . ' > ' . $child->getTitle());

                    continue;
                }

                $this->collectChildren($child->getResults(), $path . ' > ' . $child->getTitle());
            } elseif ($child instanceof ExampleResult) {
                $this->collectExample($child, $path);
            }
        }
    }

    private function collectExample(ExampleResult $example, string $path): void
    {
        $title = $path . ' > ' . $example->getTitle();

        if ($example->isError()) {
            $error = $example->getError();
            if ($error !== null) {
                $this->sections['Errors'][] = static function (OutputInterface $output) use ($title, $error): void {
                    $output->write(PHP_EOL . '  <fg=red>• ' . $title . '</>' . PHP_EOL);
                    $output->write(PHP_EOL . '  Error: ' . $error->getMessage() . PHP_EOL . PHP_EOL);
                    PrettyViews::surroundingCode($output, $error->getSurroundingCode(), $error->getLine());
                    $output->write(PHP_EOL . '  at ' . $error->getFile() . ':' . $error->getLine() . PHP_EOL);
                    foreach (array_slice($error->getFilteredTrace(), 0, 5) as $frame) {
                        $output->write('     ' . ($frame['file'] ?? '?') . ':' . ($frame['line'] ?? '?') . PHP_EOL);
                    }
                };
            }
        } elseif ($example->isFailure()) {
            $failures = array_values(array_filter(
                $example->getResults(),
                static fn(MatchResult $result): bool => $result->isFailure(),
            ));
            if ($failures !== []) {
                $this->sections['Failures'][] = function (OutputInterface $output) use ($title, $failures): void {
                    $output->write(PHP_EOL . '  <fg=red>• ' . $title . '</>' . PHP_EOL);
                    foreach ($failures as $failure) {
                        $this->matchFailure($output, $failure);
                    }
                };
            }
        } elseif ($example->isSkipped()) {
            $this->sections['Skipped'][] = static function (OutputInterface $output) use ($title): void {
                $output->write(PHP_EOL . '  <fg=cyan>• ' . $title . '</>' . PHP_EOL);
            };
        }

        foreach ($example->getWarnings() as $warning) {
            $this->sections['Warnings'][] = self::noteEntry($title, $warning);
        }
        foreach ($example->getDeprecations() as $deprecation) {
            $this->sections['Deprecations'][] = self::noteEntry($title, $deprecation);
        }
    }

    private function collectContextError(ContextResult $context, string $title): void
    {
        $error = $context->getError();
        $this->sections['Errors'][] = static function (OutputInterface $output) use ($title, $error): void {
            $output->write(PHP_EOL . '  <fg=red>• ' . $title . '</>' . PHP_EOL);
            $output->write(PHP_EOL . '  ' . $error->getType() . ': ' . $error->getMessage() . PHP_EOL . PHP_EOL);
            PrettyViews::surroundingCode($output, $error->getSurroundingCode(), $error->getLine());
            $output->write(PHP_EOL . '  at ' . $error->getFile() . ':' . $error->getLine() . PHP_EOL);
        };
    }

    private function collectFeature(FeatureResult $feature): void
    {
        foreach ($feature->getResults() as $scenario) {
            if (!$scenario instanceof ScenarioResult) {
                continue;
            }

            foreach ($scenario->getResults() as $step) {
                if (!$step instanceof StepResult || !$step->isFailure() || $step->getError() === null) {
                    continue;
                }

                $title = $feature->getTitle() . ' > ' . $scenario->getTitle() . ' > ' . $step->getTitle();
                $message = $step->getError()->getMessage();
                $this->sections['Failures'][] = static function (OutputInterface $output) use ($title, $message): void {
                    $output->write(PHP_EOL . '  <fg=red>• ' . $title . '</>' . PHP_EOL);
                    $output->write(PHP_EOL . '  ' . $message . PHP_EOL);
                };
            }
        }
    }

    /**
     * One failed expectation: a relation-mapped matcher reads as the labeled
     * pair alone (the values are the whole story); anything else keeps its
     * message with the generic expected/got pair beneath.
     */
    private function matchFailure(OutputInterface $output, MatchResult $failure): void
    {
        // The constructor's parameter names are crossed: callers pass the
        // SUBJECT first (stored as "expected") and the matcher's target value
        // second (stored as "actual"), so the view uncrosses them. The
        // relation phrase is the matcher's own declaration, carried on the
        // result; the formatter knows no matcher by name.
        $subject = $failure->getExpected();
        $target = $failure->getActual();
        $relation = $failure->getRelation();

        if ($relation !== null) {
            $relation = ($failure->isNegated() ? 'not ' : '') . $relation;
            $this->pair($output, 'expected', self::value($target), $relation, self::value($subject));
        } else {
            $output->write(PHP_EOL . '  ' . $failure->getMessage() . PHP_EOL);

            if ($subject !== null || $target !== null) {
                $this->pair($output, 'expected', self::value($target), 'got', self::value($subject));
            }
        }

        $line = $failure->getLine();
        if ($line !== null) {
            $output->write(PHP_EOL);
            PrettyViews::surroundingCode($output, $failure->getCode(), $line);
            $output->write(PHP_EOL . '  at ' . $failure->getFile() . ':' . $line . PHP_EOL);
        }
    }

    /**
     * The two labeled value lines, colons aligned to the longer label.
     */
    private function pair(OutputInterface $output, string $expectedLabel, string $expectedValue, string $actualLabel, string $actualValue): void
    {
        $width = max(strlen($expectedLabel), strlen($actualLabel));

        $output->write(PHP_EOL . '    ' . str_pad($expectedLabel, $width, ' ', STR_PAD_LEFT) . ': "' . $expectedValue . '"' . PHP_EOL);
        $output->write('    ' . str_pad($actualLabel, $width, ' ', STR_PAD_LEFT) . ': "' . $actualValue . '"' . PHP_EOL);
    }

    /**
     * A scalar-safe rendering of an expected/actual value for the generic pair.
     */
    private static function value(mixed $value): string
    {
        return match (true) {
            is_string($value) => $value,
            is_bool($value) => $value ? 'true' : 'false',
            is_null($value) => 'null',
            is_array($value) => var_export($value, true),
            is_object($value) => get_class($value) . '#' . spl_object_id($value),
            default => (string) $value,
        };
    }

    /**
     * A warning or deprecation entry: the example, the message, the location.
     *
     * @param array{message: string, file: string, line: int} $note
     * @return callable(OutputInterface): void
     */
    private static function noteEntry(string $title, array $note): callable
    {
        return static function (OutputInterface $output) use ($title, $note): void {
            $output->write(PHP_EOL . '  <fg=yellow>• ' . $title . '</>' . PHP_EOL);
            $output->write(PHP_EOL . '  ' . $note['message'] . PHP_EOL);
            $output->write(PHP_EOL . '  at ' . $note['file'] . ':' . $note['line'] . PHP_EOL);
        };
    }
}
