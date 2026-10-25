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

namespace PhpSpec\Coverage;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 * Renders a text-based code coverage report to the console.
 * Displays per-file coverage percentages with color-coded output and a total summary.
 */
final class TextReport
{
    /**
     * Renders the coverage data as a text table with file names, percentages, and line counts.
     *
     * @param array<string, array<int, int>> $data coverage data with relative file paths as keys
     * @param OutputInterface $output the console output to write to
     */
    public function render(array $data, OutputInterface $output): void
    {
        $output->writeln('');
        $output->writeln('Code Coverage Report:');

        $totalCovered = 0;
        $totalExecutable = 0;

        $maxLen = 0;
        foreach (array_keys($data) as $file) {
            $maxLen = max($maxLen, strlen($file));
        }

        foreach ($data as $file => $lines) {
            $counts = CoverageData::countLines($lines);
            $covered = $counts['covered'];
            $executable = $counts['executable'];

            $totalCovered += $covered;
            $totalExecutable += $executable;

            if ($executable === 0) {
                continue;
            }

            $percentage = new Percentage($covered, $executable);
            $dots = str_repeat('.', max(1, $maxLen - strlen($file) + 3));

            $output->writeln(sprintf(
                '  %s %s <fg=%s>%5s%%</> (%d/%d)',
                $file,
                $dots,
                $this->colourOf($percentage),
                $percentage->cut(),
                $covered,
                $executable,
            ));
        }

        $output->writeln('');

        if ($totalExecutable > 0) {
            $total = new Percentage($totalCovered, $totalExecutable);
            $output->writeln(sprintf(
                '  Total: <fg=%s>%s%%</> (%d/%d)',
                $this->colourOf($total),
                $total->cut(),
                $totalCovered,
                $totalExecutable,
            ));
        } else {
            $output->writeln('  Total: <fg=yellow>No executable lines found</>');
        }

        $output->writeln('');
    }

    private function colourOf(Percentage $percentage): string
    {
        return $percentage->complete() ? 'green' : ($percentage->value() >= 50 ? 'yellow' : 'red');
    }
}
