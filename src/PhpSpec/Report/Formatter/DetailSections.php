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

use PhpSpec\CodeGeneration\SurroundingCode;
use PhpSpec\Report\FirstDifference;
use PhpSpec\Report\Formatter\Pretty\PrettyViews;
use PhpSpec\Report\Typed;
use PhpSpec\Result\ContextResult;
use PhpSpec\Result\ExampleResult;
use PhpSpec\Result\FeatureResult;
use PhpSpec\Result\MatchResult;
use PhpSpec\Result\ScenarioResult;
use PhpSpec\Result\SpecificationResult;
use PhpSpec\Result\StepResult;
use PhpSpec\Result\SuiteResult;
use PhpSpec\Specification\ExampleError;
use PhpSpec\Specification\Expectation;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 * The end-of-run detail, grouped by kind: Failures, Errors, Warnings,
 * Deprecations, Pending and Skipped, each section printed only when it has entries.
 * Shared by the pretty and dot formatters so both tell the same story. A
 * failure whose matcher has a relation reads as a labeled pair (expected /
 * to be contained in) instead of a sentence embedding the values.
 */
final class DetailSections
{
    /** The matchers that want two values to be the same, where a first difference means something. */
    private const EQUALITY_MATCHERS = ['toBe', 'toEqual', 'toBeLike'];

    /** @var array<string, list<callable(OutputInterface): void>> */
    private array $sections = [];

    /**
     * Renders every non-empty section, in severity order.
     */
    public function render(OutputInterface $output, SuiteResult $results): void
    {
        $this->sections = ['Failures' => [], 'Errors' => [], 'Warnings' => [], 'Deprecations' => [], 'Pending' => [], 'Skipped' => []];

        foreach ($results->getResults() as $node) {
            if ($node instanceof FeatureResult) {
                $this->collectFeature($node);
            } elseif ($node instanceof SpecificationResult) {
                $this->collectChildren($node->getResults(), $node->getTitle());
            }
        }

        $colours = ['Failures' => 'red', 'Errors' => 'red', 'Warnings' => 'yellow', 'Deprecations' => 'yellow', 'Pending' => 'yellow', 'Skipped' => 'cyan'];
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
                    if ($child->getError()->missingClass() === null) {
                        $this->collectContextError($child, $path . ' > ' . $child->getTitle());
                    }

                    continue;
                }

                $this->collectChildren($child->getResults(), $path . ' > ' . $child->getTitle());
            } elseif ($child instanceof ExampleResult) {
                $this->collectExample($child, $path);
            }
        }
    }

    /**
     * A missing class is not detailed here: the run offers to generate it,
     * and that offer names it.
     */
    private function collectExample(ExampleResult $example, string $path): void
    {
        $title = $path . ' > ' . $example->getTitle();

        if ($example->isError()) {
            $error = $example->getError();
            if ($error !== null && $error->missingClass() === null) {
                $at = self::blameFor($example, $error);
                $code = (new SurroundingCode($at['file'], $at['line']))->toArray();
                $this->sections['Errors'][] = static function (OutputInterface $output) use ($title, $error, $at, $code): void {
                    $output->write(PHP_EOL . '  <fg=red>• ' . $title . '</>' . PHP_EOL);
                    $output->write(PHP_EOL . '  Error: ' . $error->getMessage() . PHP_EOL . PHP_EOL);
                    PrettyViews::surroundingCode($output, $code, $at['line']);
                    PrettyViews::location($output, $at, $error->getFilteredTrace());
                };
                $this->attachPrinted('Errors', $example->getOutput());
                $this->attachHandedOver('Errors', $example->getAttachments());
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
                $this->attachHandedOver('Failures', $example->getAttachments());
            }
        } elseif ($example->isPending()) {
            $this->sections['Pending'][] = self::reasonEntry('yellow', $title, $example->getReason());
        } elseif ($example->isSkipped()) {
            $this->sections['Skipped'][] = self::reasonEntry('cyan', $title, $example->getReason());
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
            $blame = $error->blame() ?? ['file' => $error->getFile(), 'line' => $error->getLine()];
            PrettyViews::surroundingCode($output, $error->getSurroundingCode(), $blame['line']);
            $output->write(PHP_EOL . '  at ' . $blame['file'] . ':' . $blame['line'] . PHP_EOL);
        };
    }

    /**
     * The line an example's error is shown at: the site when it is the user's
     * code, else the frame of the spec that led there, else the line that
     * declares the example, which is all there is when the error came while
     * its arguments were being resolved.
     *
     * @return array{file: string, line: int}
     */
    private static function blameFor(ExampleResult $example, ExampleError $error): array
    {
        $blame = $error->blame();
        $declared = $example->getFile() !== null && $example->getLine() !== null
            ? ['file' => $example->getFile(), 'line' => $example->getLine()]
            : null;
        $isSite = $blame !== null && $blame['file'] === $error->getFile() && $blame['line'] === $error->getLine();

        if ($blame !== null && ($isSite || $declared === null)) {
            return $blame;
        }

        if ($declared !== null) {
            $inSpec = $error->lineIn($declared['file']);

            if ($inSpec !== null) {
                return ['file' => $declared['file'], 'line' => $inSpec];
            }
        }

        return $declared ?? $blame ?? ['file' => $error->getFile(), 'line' => $error->getLine()];
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
                        $blame = $error->blame() ?? ['file' => $error->getFile(), 'line' => $error->getLine()];
                        PrettyViews::surroundingCode($output, $error->getSurroundingCode(), $blame['line']);
                        PrettyViews::location($output, $blame, $error->getFilteredTrace());
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

            // Handed over by the scenario, not by any one of its steps: whichever
            // step failed, the log the scenario was watching is the same log.
            $this->attachHandedOver('Failures', $scenario->getAttachments());
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

            // Two long strings that are meant to be equal part at one place,
            // and the pair shows that place rather than two heads and tails
            // that read alike.
            $offset = in_array($matcher, self::EQUALITY_MATCHERS, true) && is_string($subject) && is_string($target)
                && max(strlen($subject), strlen($target)) > Typed::STRING_MAX
                ? FirstDifference::between($target, $subject)['offset'] ?? null
                : null;

            if (is_int($offset)) {
                $this->pair($output, 'expected', self::window($subject, $offset), $label, self::window($target, $offset));
                $output->write('  first difference at offset ' . $offset . PHP_EOL);
            } else {
                $this->pair($output, 'expected', Typed::value($subject), $label, Typed::value($target));
            }
        } else {
            $output->write(PHP_EOL . '  ' . $failure->getMessage() . PHP_EOL);

            // A matcher with no target has nothing to put opposite the subject,
            // and "expected: N/A" is a line that tells the reader nothing.
            if (($subject !== null || $target !== null) && $target !== Expectation::NO_TARGET) {
                $this->pair($output, 'expected', Typed::value($target), 'got', Typed::value($subject));
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

        $output->write(PHP_EOL . '  ' . str_pad($firstLabel, $width, ' ', STR_PAD_LEFT) . ': ' . $firstValue . PHP_EOL);
        $output->write('  ' . str_pad($secondLabel, $width, ' ', STR_PAD_LEFT) . ': ' . $secondValue . PHP_EOL);
    }

    /**
     * A string from around the place two strings part, so the character that
     * differs is on the line instead of under a [...] marker.
     */
    private static function window(string $value, int $offset): string
    {
        $start = max(0, $offset - 20);
        $cut = substr($value, $start, Typed::STRING_MAX);

        return '"' . ($start > 0 ? '[...]' : '') . Typed::escaped($cut) . (strlen($value) > $start + Typed::STRING_MAX ? '[...]' : '') . '"';
    }

    /**
     * An object named by what it is, with its properties when the name alone
     * tells nothing: two points that differ read Point{x: 1, y: 2} against
     * Point{x: 1, y: 3}, not Point against Point. An enum, a throwable or
     * anything that describes itself is named as it describes itself.
     */
    /**
     * Adds what the spec or scenario handed over about itself, under the name
     * it was handed over with, so a watched log reads next to the failure it
     * explains.
     *
     * @param array<string, string|array{error: string}> $attachments
     */
    private function attachHandedOver(string $section, array $attachments): void
    {
        foreach ($attachments as $name => $value) {
            $text = is_string($value) ? $value : 'could not be read: ' . $value['error'];

            $this->sections[$section][] = static function (OutputInterface $output) use ($name, $text): void {
                $output->write(PHP_EOL . '  <fg=gray>' . $name . ':</>');
                PrettyViews::printedOutput($output, $text, 2);
                $output->write(PHP_EOL);
            };
        }
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

    private static function reasonEntry(string $colour, string $title, ?string $reason): callable
    {
        return static function (OutputInterface $output) use ($colour, $title, $reason): void {
            $output->write(PHP_EOL . '  <fg=' . $colour . '>• ' . $title . '</>' . PHP_EOL);

            if ($reason !== null) {
                $output->write('    ' . $reason . PHP_EOL);
            }
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
