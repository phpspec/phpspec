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

namespace PhpSpec\Console\Command\Run;

use DOMException;
use PhpSpec\Coverage\CloverReport;
use PhpSpec\Coverage\CoverageCollector;
use PhpSpec\Coverage\HtmlReport;
use PhpSpec\Coverage\TextReport;
use Symfony\Component\Console\Output\OutputInterface as Output;

/**
 * @internal
 * Manages the complete code coverage lifecycle: availability check, collection start/stop,
 * report rendering (text, Clover XML, HTML), and minimum threshold enforcement.
 */
final class CoverageReporter
{
    private ?CoverageCollector $collector = null;

    /**
     * Checks Xdebug availability and starts coverage collection.
     *
     * @param Output $output the console output for error messages
     * @return bool true if collection started, false if unavailable
     */
    public function start(Output $output): bool
    {
        if (!CoverageCollector::isAvailable()) {
            $output->writeln('<fg=red>Code coverage requires Xdebug with coverage mode enabled</>');
            return false;
        }
        $this->collector = new CoverageCollector();
        $this->collector->start();
        return true;
    }

    /**
     * Stops collection and renders all requested coverage reports.
     *
     * @param Output $output the console output
     * @param string $srcPath the source directory to filter coverage data
     * @param bool $showText whether to render the text coverage report
     * @param string|null $cloverPath path for Clover XML report, or null to skip
     * @param string|null $htmlPath directory for HTML report, or null to skip
     * @param string|null $coverageMin minimum coverage percentage threshold, or null to skip
     * @return int|null returns 1 if below minimum threshold, null otherwise
     * @throws DOMException
     */
    public function report(
        Output $output,
        string $srcPath,
        bool $showText,
        ?string $cloverPath,
        ?string $htmlPath,
        ?string $coverageMin,
    ): ?int {
        if ($this->collector === null) {
            return null;
        }

        $this->collector->stop();
        $covData = $this->collector->filter($srcPath);

        if ($showText) {
            (new TextReport())->render($covData, $output);
        }
        if ($cloverPath !== null) {
            (new CloverReport())->render($covData, $cloverPath);
            $output->writeln("  Clover report: $cloverPath");
        }
        if ($htmlPath !== null) {
            (new HtmlReport())->render($covData, $htmlPath);
            $output->writeln("  HTML report: $htmlPath/index.html");
        }
        if ($coverageMin !== null) {
            $totalCovered = 0;
            $totalExecutable = 0;
            foreach ($covData as $lines) {
                $counts = CoverageCollector::countLines($lines);
                $totalCovered += $counts['covered'];
                $totalExecutable += $counts['executable'];
            }
            $pct = $totalExecutable > 0 ? ($totalCovered / $totalExecutable) * 100 : 0;
            $min = (float) $coverageMin;
            if ($pct < $min) {
                $output->writeln(sprintf(
                    '<fg=red>Code coverage %.1f%% is below the required %.1f%%</>',
                    $pct,
                    $min,
                ));
                return 1;
            }
            $output->writeln(sprintf('<fg=green>Code coverage %.1f%% meets the required %.1f%%</>', $pct, $min));
        }

        return null;
    }
}
