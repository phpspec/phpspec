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
use PhpSpec\Report\AbstractFormatter;
use PhpSpec\Result\Counts;
use PhpSpec\Result\ExampleResult;
use PhpSpec\Result\StepResult;
use PhpSpec\Result\SuiteResult;
use PhpSpec\Results;
use Symfony\Component\Console\Terminal;

/**
 * Renders spec results as single-character progress indicators: dot for pass,
 * F for failure, E for error, P for pending. Appends a summary with counts and errors.
 */
final class Dot extends AbstractFormatter
{
    private int $dotsPerLine = 60;
    private int $subtotalWidth = 3;
    private int $col = 0;
    private int $total = 0;

    /**
     * Calculates line widths and delegates to the parent format pipeline.
     */
    public function format(SuiteResult $results): void
    {
        $totalExamples = $this->countExamples($results);
        $this->subtotalWidth = strlen((string) $totalExamples);

        $width = (new Terminal())->getWidth() ?: 80;
        $lineWidth = (int) floor($width * 0.9);
        // Suffix is " (NNN)" = 3 + subtotalWidth
        $this->dotsPerLine = max(1, $lineWidth - 3 - $this->subtotalWidth);
        $this->col = 0;
        $this->total = 0;

        parent::format($results);
    }

    /**
     * Outputs an initial blank line before the progress dots.
     */
    public function begin(): void
    {
        $this->output->writeln('');
    }

    /**
     * Outputs progress characters for each example or step in the result tree.
     */
    public function printResult(Results $result): void
    {
        $this->printDots($result);
    }

    /**
     * Outputs the final summary: errors, warnings, deprecations, notices, and counts.
     */
    public function end(SuiteResult $results): void
    {
        if ($this->col > 0) {
            $this->printSubtotal();
        }
        $this->output->writeln('');

        $this->formatErrors($results);
        $this->formatWarnings($results);
        $this->formatDeprecations($results);
        $this->formatNotices($results);

        $counts = new Counts($results);
        $c = $counts->toArray();

        if (!empty($c['features'])) {
            $this->output->writeln(
                $c['features'] . ' feature' . ($c['features'] != 1 ? 's' : '') . ', '
                . $c['scenarios'] . ' scenario' . ($c['scenarios'] != 1 ? 's' : '') . ', '
                . $c['steps'] . ' step' . ($c['steps'] != 1 ? 's' : '') . ' ('
                . $this->formatStepParts($c) . ')',
            );
        }

        if (empty($c['features'])) {
            $this->output->writeln($c['specs'] . ' spec' . ($c['specs'] != 1 ? 's' : ''));
        }

        if ($c['examples'] > 0) {
            $parts = [];
            if ($c['passes']) {
                $parts[] = "<fg=green>{$c['passes']} passes</>";
            }
            if ($c['failures']) {
                $parts[] = "<fg=red>{$c['failures']} failures</>";
            }
            if ($c['errors']) {
                $parts[] = "<fg=red>{$c['errors']} errors</>";
            }
            if ($c['pending']) {
                $parts[] = "<fg=yellow>{$c['pending']} pending</>";
            }
            if ($c['exampleSkipped']) {
                $parts[] = "<fg=cyan>{$c['exampleSkipped']} skipped</>";
            }
            if ($c['warnings']) {
                $parts[] = "<fg=yellow>{$c['warnings']} warning" . ($c['warnings'] != 1 ? 's' : '') . '</>';
            }
            if ($c['deprecations']) {
                $parts[] = "<fg=yellow>{$c['deprecations']} deprecation" . ($c['deprecations'] != 1 ? 's' : '') . '</>';
            }
            if ($c['notices']) {
                $parts[] = "<fg=yellow>{$c['notices']} notice" . ($c['notices'] != 1 ? 's' : '') . '</>';
            }

            $this->output->writeln($c['examples'] . ' example' . ($c['examples'] != 1 ? 's' : '') . ' (' . implode(', ', $parts) . ')');
        }

        $duration = $results->getDuration();
        if ($duration > 0) {
            $this->output->writeln(sprintf('Finished in %.4f seconds', $duration));
        }
    }

    /**
     * Formats the step outcome breakdown (passed, failed, pending, undefined, skipped).
     *
     * @param array<string, int> $c
     */
    private function formatStepParts(array $c): string
    {
        $parts = [];
        if ($c['stepPasses']) {
            $parts[] = "<fg=green>{$c['stepPasses']} passed</>";
        }
        if ($c['stepFailures']) {
            $parts[] = "<fg=red>{$c['stepFailures']} failed</>";
        }
        if ($c['pending']) {
            $parts[] = "<fg=yellow>{$c['pending']} pending</>";
        }
        if ($c['undefined']) {
            $parts[] = "<fg=bright-blue>{$c['undefined']} undefined</>";
        }
        if ($c['skipped']) {
            $parts[] = "<fg=cyan>{$c['skipped']} skipped</>";
        }
        return implode(', ', $parts);
    }

    /**
     * Recursively traverses results and outputs a character for each example.
     */
    private function printDots(Results $results): void
    {
        foreach ($results->getResults() as $result) {
            if ($result instanceof ExampleResult) {
                $this->formatExample($result);
            } elseif ($result instanceof StepResult) {
                $this->formatStep($result);
            } elseif ($result instanceof Results) {
                $this->printDots($result);
            }
        }
    }

    /**
     * Outputs a single character representing the example's outcome.
     */
    private function formatExample(ExampleResult $example): void
    {
        if ($example->isPending()) {
            $this->output->write('<fg=yellow>P</>');
        } elseif ($example->isSkipped()) {
            $this->output->write('<fg=cyan>S</>');
        } elseif ($example->isError()) {
            $this->output->write('<fg=red>E</>');
        } elseif ($example->isFailure()) {
            $this->output->write('<fg=red>F</>');
        } else {
            $this->output->write('<fg=green>.</>');
        }

        $this->col++;
        $this->total++;

        if ($this->col >= $this->dotsPerLine) {
            $this->printSubtotal();
        }
    }

    /**
     * Outputs a single character representing the step's outcome.
     */
    private function formatStep(StepResult $step): void
    {
        if ($step->isPending() || $step->isUndefined()) {
            $this->output->write('<fg=yellow>P</>');
        } elseif ($step->isFailure()) {
            $this->output->write('<fg=red>F</>');
        } elseif ($step->isSkipped()) {
            $this->output->write('<fg=cyan>*</>');
        } else {
            $this->output->write('<fg=green>.</>');
        }

        $this->col++;
        $this->total++;

        if ($this->col >= $this->dotsPerLine) {
            $this->printSubtotal();
        }
    }

    /**
     * Outputs the right-aligned subtotal at the end of a progress line.
     */
    private function printSubtotal(): void
    {
        $padding = str_repeat(' ', $this->dotsPerLine - $this->col);
        $num = sprintf('%' . $this->subtotalWidth . 'd', $this->total);
        $this->output->writeln("{$padding} <fg=gray>{$num}</>");
        $this->col = 0;
    }

    /**
     * Recursively counts the total number of examples and steps in the result tree.
     */
    private function countExamples(Results $results): int
    {
        $count = 0;
        foreach ($results->getResults() as $result) {
            if ($result instanceof ExampleResult || $result instanceof StepResult) {
                $count++;
            } elseif ($result instanceof Results) {
                $count += $this->countExamples($result);
            }
        }
        return $count;
    }

    /**
     * Outputs numbered error and failure messages collected from all results.
     */
    private function formatErrors(Results $results): void
    {
        $errors = $this->collectErrors($results);
        if (empty($errors)) {
            return;
        }

        foreach ($errors as $i => $error) {
            $num = $i + 1;
            $this->output->writeln("  <fg=red>$num) {$error['title']}</>");
            $this->output->writeln("     <fg=red>{$error['message']}</>");
            $this->output->writeln('');
        }
    }

    /**
     * Outputs warnings with surrounding source code context.
     */
    private function formatWarnings(Results $results): void
    {
        $warnings = $this->collectWarnings($results);
        if (empty($warnings)) {
            return;
        }

        foreach ($warnings as $warning) {
            $this->output->writeln("  <fg=yellow>⚠️ {$warning['title']}</>");
            $this->output->writeln('');
            $this->output->writeln("    Warning: {$warning['message']}");
            $this->output->writeln('');
            $surrounding = (new SurroundingCode($warning['file'], $warning['line']))->toArray();
            $lastLine = array_key_last($surrounding);
            $decimalPlace = strlen((string) $lastLine);
            foreach ($surrounding as $lineNum => $code) {
                $indent = strlen((string) $lineNum) < $decimalPlace ? ' ' : '';
                if ($lineNum === $warning['line']) {
                    $this->output->writeln("  <fg=red> ></> $indent<options=bold>$lineNum</>  <fg=gray>|</> <fg=red>$code</>");
                } else {
                    $this->output->writeln("     $indent<fg=gray>$lineNum  |</> $code");
                }
            }
            $this->output->writeln('');
            $this->output->writeln("    at {$warning['file']}:{$warning['line']}");
            $this->output->writeln('');
        }
    }

    /**
     * Recursively collects warning details from all example results.
     *
     * @param array<int, array{title: string, message: string, file: string, line: int}> $warnings
     * @return array<int, array{title: string, message: string, file: string, line: int}>
     */
    private function collectWarnings(Results $results, array &$warnings = []): array
    {
        foreach ($results->getResults() as $result) {
            if ($result instanceof ExampleResult) {
                if ($result->hasWarnings()) {
                    foreach ($result->getWarnings() as $warning) {
                        $warnings[] = [
                            'title' => $result->getTitle(),
                            'message' => $warning['message'],
                            'file' => $warning['file'],
                            'line' => $warning['line'],
                        ];
                    }
                }
            } elseif ($result instanceof Results) {
                $this->collectWarnings($result, $warnings);
            }
        }
        return $warnings;
    }

    /**
     * Outputs deprecations with surrounding source code context.
     */
    private function formatDeprecations(Results $results): void
    {
        $deprecations = $this->collectDeprecations($results);
        if (empty($deprecations)) {
            return;
        }

        foreach ($deprecations as $deprecation) {
            $this->output->writeln("  <fg=yellow>⚠️ {$deprecation['title']}</>");
            $this->output->writeln('');
            $this->output->writeln("    Deprecated: {$deprecation['message']}");
            $this->output->writeln('');
            $surrounding = (new SurroundingCode($deprecation['file'], $deprecation['line']))->toArray();
            $lastLine = array_key_last($surrounding);
            $decimalPlace = strlen((string) $lastLine);
            foreach ($surrounding as $lineNum => $code) {
                $indent = strlen((string) $lineNum) < $decimalPlace ? ' ' : '';
                if ($lineNum === $deprecation['line']) {
                    $this->output->writeln("  <fg=red> ></> $indent<options=bold>$lineNum</>  <fg=gray>|</> <fg=red>$code</>");
                } else {
                    $this->output->writeln("     $indent<fg=gray>$lineNum  |</> $code");
                }
            }
            $this->output->writeln('');
            $this->output->writeln("    at {$deprecation['file']}:{$deprecation['line']}");
            $this->output->writeln('');
        }
    }

    /**
     * Outputs notices with surrounding source code context.
     */
    private function formatNotices(Results $results): void
    {
        $notices = $this->collectNotices($results);
        if (empty($notices)) {
            return;
        }

        foreach ($notices as $notice) {
            $this->output->writeln("  <fg=yellow>⚠️ {$notice['title']}</>");
            $this->output->writeln('');
            $this->output->writeln("    Notice: {$notice['message']}");
            $this->output->writeln('');
            $surrounding = (new SurroundingCode($notice['file'], $notice['line']))->toArray();
            $lastLine = array_key_last($surrounding);
            $decimalPlace = strlen((string) $lastLine);
            foreach ($surrounding as $lineNum => $code) {
                $indent = strlen((string) $lineNum) < $decimalPlace ? ' ' : '';
                if ($lineNum === $notice['line']) {
                    $this->output->writeln("  <fg=red> ></> $indent<options=bold>$lineNum</>  <fg=gray>|</> <fg=red>$code</>");
                } else {
                    $this->output->writeln("     $indent<fg=gray>$lineNum  |</> $code");
                }
            }
            $this->output->writeln('');
            $this->output->writeln("    at {$notice['file']}:{$notice['line']}");
            $this->output->writeln('');
        }
    }

    /**
     * Recursively collects deprecation details from all example results.
     *
     * @param array<int, array{title: string, message: string, file: string, line: int}> $deprecations
     * @return array<int, array{title: string, message: string, file: string, line: int}>
     */
    private function collectDeprecations(Results $results, array &$deprecations = []): array
    {
        foreach ($results->getResults() as $result) {
            if ($result instanceof ExampleResult) {
                if ($result->hasDeprecations()) {
                    foreach ($result->getDeprecations() as $deprecation) {
                        $deprecations[] = [
                            'title' => $result->getTitle(),
                            'message' => $deprecation['message'],
                            'file' => $deprecation['file'],
                            'line' => $deprecation['line'],
                        ];
                    }
                }
            } elseif ($result instanceof Results) {
                $this->collectDeprecations($result, $deprecations);
            }
        }
        return $deprecations;
    }

    /**
     * Recursively collects notice details from all example results.
     *
     * @param array<int, array{title: string, message: string, file: string, line: int}> $notices
     * @return array<int, array{title: string, message: string, file: string, line: int}>
     */
    private function collectNotices(Results $results, array &$notices = []): array
    {
        foreach ($results->getResults() as $result) {
            if ($result instanceof ExampleResult) {
                if ($result->hasNotices()) {
                    foreach ($result->getNotices() as $notice) {
                        $notices[] = [
                            'title' => $result->getTitle(),
                            'message' => $notice['message'],
                            'file' => $notice['file'],
                            'line' => $notice['line'],
                        ];
                    }
                }
            } elseif ($result instanceof Results) {
                $this->collectNotices($result, $notices);
            }
        }
        return $notices;
    }

    /**
     * Recursively collects error and failure details from all example results.
     *
     * @param array<int, array{title: string, message: string}> $errors
     * @return array<int, array{title: string, message: string}>
     */
    private function collectErrors(Results $results, array &$errors = []): array
    {
        foreach ($results->getResults() as $result) {
            if ($result instanceof ExampleResult) {
                if ($result->isError() || $result->isFailure()) {
                    $errors[] = [
                        'title' => $result->getTitle(),
                        'message' => $result->getMessage(),
                    ];
                }
            } elseif ($result instanceof StepResult) {
                if ($result->isFailure() && $result->getError() !== null) {
                    $errors[] = [
                        'title' => $result->getTitle(),
                        'message' => $result->getError()->getMessage(),
                    ];
                }
            } elseif ($result instanceof Results) {
                $this->collectErrors($result, $errors);
            }
        }
        return $errors;
    }
}
