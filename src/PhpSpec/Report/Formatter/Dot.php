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
use PhpSpec\Report\Formatter\Pretty\PrettyViews;
use PhpSpec\Result\Counts;
use PhpSpec\Result\ExampleResult;
use PhpSpec\Result\SpecificationResult;
use PhpSpec\Result\StepResult;
use PhpSpec\Result\SuiteResult;
use PhpSpec\Results;
use Symfony\Component\Console\Terminal;

/**
 * @internal
 * Renders spec results as single-character progress indicators: dot for pass,
 * F for failure, E for error, P for pending. Appends a summary with counts and errors.
 */
final class Dot extends AbstractFormatter
{
    private int $dotsPerLine = 60;
    private int $subtotalWidth = 3;
    private int $col = 0;
    private int $total = 0;
    private bool $hasResults = false;
    private bool $blocked = false;

    /**
     * Sizes the subtotal column to the whole suite before the pipeline starts;
     * results streamed in one by one keep the default width.
     */
    public function format(SuiteResult $results): void
    {
        $this->subtotalWidth = strlen((string) $this->countExamples($results));

        parent::format($results);
    }

    /**
     * Fits the dots to the terminal before the first result arrives.
     */
    public function begin(): void
    {
        $width = (new Terminal())->getWidth() ?: 80;
        $lineWidth = (int) floor($width * 0.9);
        // Suffix is " (NNN)" = 3 + subtotalWidth
        $this->dotsPerLine = max(1, $lineWidth - 3 - $this->subtotalWidth);
        $this->col = 0;
        $this->total = 0;
    }

    /**
     * Outputs progress characters for each example or step in the result tree.
     * A specification blocked on a class that does not exist yet is left to
     * the run, which offers that class where its error would have been reported.
     */
    public function printResult(Results $result): void
    {
        if ($result instanceof SpecificationResult && $result->isBlockedOnMissingClass()) {
            $this->blocked = true;

            return;
        }

        if (!$this->hasResults) {
            $this->output->writeln('Once you spec, you never go back!');
            $this->output->writeln('');
            $this->hasResults = true;
        }

        $this->printDots($result);
    }

    /**
     * Outputs the final summary: errors, warnings, deprecations, notices, and counts.
     */
    public function end(SuiteResult $results): void
    {
        if (!$this->hasResults) {
            if (!$this->blocked) {
                $this->output->writeln($this->nothingFound);
            }

            return;
        }

        if ($this->col > 0) {
            $this->printSubtotal();
        }
        $this->output->writeln('');

        // The same sectioned detail as the pretty formatter: the dots are the
        // progress, the catch-up story at the bottom is identical.
        (new DetailSections())->render($this->output, $results);
        $this->formatNotices($results);
        $this->output->writeln('');

        PrettyViews::counts($this->output, (new Counts($results))->toArray(), $results->getDuration());
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
        } elseif ($example->isRisky()) {
            $this->output->write('<fg=yellow>R</>');
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
        if ($step->isPending()) {
            $this->output->write('<fg=yellow>P</>');
        } elseif ($step->isUndefined()) {
            $this->output->write('<fg=bright-blue>U</>');
        } elseif ($step->isError()) {
            $this->output->write('<fg=red>E</>');
        } elseif ($step->isFailure()) {
            $this->output->write('<fg=red>F</>');
        } elseif ($step->isSkipped()) {
            $this->output->write('<fg=cyan>S</>');
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
     * Outputs notices with surrounding source code context.
     */
    private function formatNotices(Results $results): void
    {
        $notices = $this->collectNotices($results);
        if (empty($notices)) {
            return;
        }

        foreach ($notices as $notice) {
            $this->output->writeln("  <fg=yellow>⚠ {$notice['title']}</>");
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

}
