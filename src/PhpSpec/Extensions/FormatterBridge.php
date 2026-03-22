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

namespace PhpSpec\Extensions;

use PhpSpec\Report\Formatter;
use PhpSpec\Result\ExampleResult;
use PhpSpec\Result\SuiteResult;
use PhpSpec\Results;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 * Adapts a FormatterExtension into the Formatter interface used by the runner.
 */
final class FormatterBridge implements Formatter
{
    private int $total = 0;
    private int $passed = 0;
    private int $failed = 0;
    private int $pending = 0;
    private int $errors = 0;

    /**
     * @param FormatterExtension $extension The formatter extension to bridge
     * @param OutputInterface    $output    The Symfony console output to write to
     */
    public function __construct(
        private readonly FormatterExtension $extension,
        private readonly OutputInterface $output,
    ) {}

    /**
     * Formats a complete suite result by iterating over all specification results.
     *
     * @param SuiteResult $results The suite result containing all specification outcomes
     *
     * @return void
     */
    public function format(SuiteResult $results): void
    {
        $this->begin();
        foreach ($results->getResults() as $result) {
            $this->printResult($result);
        }
        $this->end($results);
    }

    /**
     * Outputs an initial blank line before any results are printed.
     *
     * @return void
     */
    public function begin(): void
    {
        $this->output->writeln('');
    }

    /**
     * Prints a single result by recursively walking its children.
     *
     * @param Results $result The specification or nested result to print
     *
     * @return void
     */
    public function printResult(Results $result): void
    {
        $this->walkResults($result);
    }

    /**
     * Outputs the summary line and suite duration after all results are printed.
     *
     * @param SuiteResult $results The suite result used to retrieve duration
     *
     * @return void
     */
    public function end(SuiteResult $results): void
    {
        $this->output->writeln('');
        $summary = $this->extension->formatSummary($this->total, $this->passed, $this->failed, $this->pending, $this->errors);
        $this->output->writeln($summary);

        $duration = $results->getDuration();
        if ($duration > 0) {
            $this->output->writeln(sprintf('Finished in %.4f seconds', $duration));
        }
    }

    private function walkResults(Results $result): void
    {
        foreach ($result->getResults() as $child) {
            if ($child instanceof ExampleResult) {
                $this->formatExample($child);
            } elseif ($child instanceof Results) {
                $this->walkResults($child);
            }
        }
    }

    private function formatExample(ExampleResult $example): void
    {
        $this->total++;

        if ($example->isPending() || $example->isSkipped()) {
            $this->pending++;
            $line = $this->extension->formatPending($example->getTitle());
        } elseif ($example->isError()) {
            $this->errors++;
            $line = $this->extension->formatError($example->getTitle(), $example->getMessage());
        } elseif ($example->isFailure()) {
            $this->failed++;
            $line = $this->extension->formatFail($example->getTitle(), $example->getMessage());
        } else {
            $this->passed++;
            $line = $this->extension->formatPass($example->getTitle());
        }

        $this->output->writeln($line);
    }
}
