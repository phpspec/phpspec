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
            $counts = CoverageCollector::countLines($lines);
            $covered = $counts['covered'];
            $executable = $counts['executable'];

            $totalCovered += $covered;
            $totalExecutable += $executable;

            if ($executable === 0) {
                continue;
            }

            $pct = ($covered / $executable) * 100;
            $dots = str_repeat('.', max(1, $maxLen - strlen($file) + 3));
            $color = $pct === 100.0 ? 'green' : ($pct >= 50 ? 'yellow' : 'red');

            $output->writeln(sprintf(
                '  %s %s <fg=%s>%5.1f%%</> (%d/%d)',
                $file,
                $dots,
                $color,
                $pct,
                $covered,
                $executable,
            ));
        }

        $output->writeln('');

        if ($totalExecutable > 0) {
            $totalPct = ($totalCovered / $totalExecutable) * 100;
            $color = $totalPct === 100.0 ? 'green' : ($totalPct >= 50 ? 'yellow' : 'red');
            $output->writeln(sprintf(
                '  Total: <fg=%s>%.1f%%</> (%d/%d)',
                $color,
                $totalPct,
                $totalCovered,
                $totalExecutable,
            ));
        } else {
            $output->writeln('  Total: <fg=yellow>No executable lines found</>');
        }

        $output->writeln('');
    }
}
