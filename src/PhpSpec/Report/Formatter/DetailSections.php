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

use PhpSpec\ObjectName;
use PhpSpec\Report\Formatter\Pretty\PrettyViews;
use PhpSpec\Result\ContextResult;
use PhpSpec\Result\ExampleResult;
use PhpSpec\Result\FeatureResult;
use PhpSpec\Result\MatchResult;
use PhpSpec\Result\ScenarioResult;
use PhpSpec\Result\SpecificationResult;
use PhpSpec\Result\StepResult;
use PhpSpec\Result\SuiteResult;
use PhpSpec\Specification\Expectation;
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
                $this->attachPrinted('Errors', $example->getOutput());
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
                $this->attachPrinted('Failures', $example->getOutput());
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
                if (!$step instanceof StepResult) {
                    continue;
                }

                $title = $feature->getTitle() . ' > ' . $scenario->getTitle() . ' > ' . $step->getTitle();

                // A step whose code threw is an error, exactly like an example
                // whose code threw; only a failed expectation is a failure.
                $error = $step->getError();
                if ($step->isError() && $error !== null) {
                    $this->sections['Errors'][] = static function (OutputInterface $output) use ($title, $error): void {
                        $output->write(PHP_EOL . '  <fg=red>• ' . $title . '</>' . PHP_EOL);
                        $output->write(PHP_EOL . '  ' . $error->getType() . ': ' . $error->getMessage() . PHP_EOL . PHP_EOL);
                        PrettyViews::surroundingCode($output, $error->getSurroundingCode(), $error->getLine());
                        $output->write(PHP_EOL . '  at ' . $error->getFile() . ':' . $error->getLine() . PHP_EOL);
                        foreach (array_slice($error->getFilteredTrace(), 0, 5) as $frame) {
                            $output->write('     ' . ($frame['file'] ?? '?') . ':' . ($frame['line'] ?? '?') . PHP_EOL);
                        }
                    };
                    $this->attachPrinted('Errors', $step->getOutput());
                } elseif ($step->isFailure() && $error !== null) {
                    $message = $error->getMessage();
                    $this->sections['Failures'][] = static function (OutputInterface $output) use ($title, $message): void {
                        $output->write(PHP_EOL . '  <fg=red>• ' . $title . '</>' . PHP_EOL);
                        $output->write(PHP_EOL . '  ' . $message . PHP_EOL);
                    };
                    $this->attachPrinted('Failures', $step->getOutput());
                }

                foreach ($step->getWarnings() as $warning) {
                    $section = in_array($warning['severity'], [E_DEPRECATED, E_USER_DEPRECATED], true) ? 'Deprecations' : 'Warnings';
                    $this->sections[$section][] = self::noteEntry($title, $warning);
                }
            }
        }
    }

    /**
     * One failed expectation: a named matcher reads as a labeled pair, its
     * label inferred from the matcher's own name (toContain reads "expected X
     * to contain Y"); an anonymous failure keeps its message with the generic
     * pair beneath.
     */
    private function matchFailure(OutputInterface $output, MatchResult $failure): void
    {
        // The constructor's parameter names are crossed: callers pass the
        // SUBJECT first (stored as "expected") and the matcher's target value
        // second (stored as "actual"), so the view uncrosses them.
        $subject = $failure->getExpected();
        $target = $failure->getActual();
        $matcher = $failure->getMatcher();

        // A target the matcher's own name already states ("to be true") is not
        // read back as a label and a value, which says the same thing twice.
        // The message says it once, and the pair beneath names both sides.
        if ($matcher !== null && $target !== null && !$failure->isTargetImplied()) {
            $label = ($failure->isNegated() ? 'not ' : '') . self::phrase($matcher);
            $this->pair($output, 'expected', self::value($subject), $label, self::value($target));
        } else {
            $output->write(PHP_EOL . '  ' . $failure->getMessage() . PHP_EOL);

            // A matcher with no target has nothing to put opposite the subject,
            // and "expected: N/A" is a line that tells the reader nothing.
            if (($subject !== null || $target !== null) && $target !== Expectation::NO_TARGET) {
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
     * The matcher's name as words: "toContain" reads "to contain",
     * "toBeGreaterThan" reads "to be greater than".
     */
    private static function phrase(string $matcher): string
    {
        return strtolower(trim((string) preg_replace('/(?<!^)[A-Z]/', ' $0', $matcher)));
    }

    /**
     * The two labeled value lines, colons aligned to the longer label.
     */
    private function pair(OutputInterface $output, string $firstLabel, string $firstValue, string $secondLabel, string $secondValue): void
    {
        $width = max(strlen($firstLabel), strlen($secondLabel));

        $output->write(PHP_EOL . '  ' . str_pad($firstLabel, $width, ' ', STR_PAD_LEFT) . ': "' . $firstValue . '"' . PHP_EOL);
        $output->write('  ' . str_pad($secondLabel, $width, ' ', STR_PAD_LEFT) . ': "' . $secondValue . '"' . PHP_EOL);
    }

    /**
     * A value as the pair shows it: newlines escaped, and a long or multiline
     * string capped to its first thirty and last thirty characters around a
     * [...] marker, because the pair names the difference, not the whole blob.
     */
    private static function value(mixed $value): string
    {
        if (is_string($value)) {
            $capped = strlen($value) > 60
                ? substr($value, 0, 30) . '[...]' . substr($value, -30)
                : $value;

            return str_replace(["\r\n", "\n", "\r"], '\n', $capped);
        }

        return match (true) {
            is_bool($value) => $value ? 'true' : 'false',
            is_null($value) => 'null',
            is_array($value) => var_export($value, true),
            // Named by what it is, not by which instance it was: two runs of the
            // same failure read the same, and "Money#180" told nobody anything.
            is_object($value) => ObjectName::of($value),
            default => (string) $value,
        };
    }

    /**
     * Adds what the subject printed underneath the entry it belongs to, so the
     * dump that explains a failure is read next to it instead of somewhere up
     * the terminal. Nothing is added when nothing was printed.
     */
    private function attachPrinted(string $section, string $printed): void
    {
        if (trim($printed) === '') {
            return;
        }

        $this->sections[$section][] = static function (OutputInterface $output) use ($printed): void {
            $output->write(PHP_EOL . '  <fg=gray>printed:</>');
            PrettyViews::printedOutput($output, $printed, 2);
            $output->write(PHP_EOL);
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
