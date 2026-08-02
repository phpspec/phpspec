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
use PhpSpec\Coverage\CoverageOptions;
use PhpSpec\Coverage\CoverageRegistry;
use PhpSpec\Coverage\CoverageVerdict;
use PhpSpec\Coverage\Driver\XdebugDriver;
use PhpSpec\Coverage\HtmlReport;
use PhpSpec\Coverage\JsonReport;
use PhpSpec\Coverage\JsonReportBuilder;
use PhpSpec\Coverage\PerExampleCollector;
use PhpSpec\Coverage\TextReport;
use PhpSpec\RealFilesystem;
use Symfony\Component\Console\Output\OutputInterface as Output;

/**
 * @internal
 * Manages the complete code coverage lifecycle: availability check, collection
 * start/stop, report rendering (text, Clover XML, HTML, JSON), and minimum
 * threshold enforcement. Collection runs in one of two modes: whole-suite
 * (one aggregate collection around the entire run) or per-example (the runner
 * cycles collection around each example so coverage can be attributed to it).
 */
final class CoverageReporter
{
    private ?CoverageCollector $collector = null;

    /**
     * Checks Xdebug availability and starts coverage collection.
     *
     * In per-example mode no collection is started here; instead a collector
     * is activated in the CoverageRegistry and the runner cycles it around
     * each example.
     *
     * @param Output $output the console output for error messages
     * @param bool $perExample whether to collect coverage per example
     * @return bool true if collection started, false if unavailable
     */
    public function start(Output $output, bool $perExample = false): bool
    {
        if (!CoverageCollector::isAvailable()) {
            $output->writeln('<fg=red>Code coverage requires Xdebug with coverage mode enabled</>');

            return false;
        }

        if ($perExample) {
            CoverageRegistry::activate(new PerExampleCollector(new XdebugDriver()));

            return true;
        }

        $this->collector = new CoverageCollector();
        $this->collector->start();

        return true;
    }

    /**
     * Stops collection and renders all requested coverage reports.
     *
     * When a per-example collector is active and a partial path is set (the
     * parallel worker case), the raw collector state is dumped there instead
     * of rendering any report.
     *
     * @param Output $output the console output
     * @param CoverageOptions $options the requested reports and their destinations
     * @return CoverageVerdict|null what the run covered, or null when nothing was
     *                              collected or the state was only dumped for a worker
     * @throws DOMException
     */
    public function report(Output $output, CoverageOptions $options): ?CoverageVerdict
    {
        $perExampleCollector = CoverageRegistry::collector();

        if ($perExampleCollector !== null) {
            CoverageRegistry::reset();

            if ($options->partialPath !== null) {
                $this->writePartial($perExampleCollector, $options->partialPath);

                return null;
            }

            $covData = CoverageCollector::filterData($perExampleCollector->getAggregate(), $options->srcPath);

            if ($options->jsonPath !== null) {
                $this->renderJson($output, $perExampleCollector, $options->srcPath, $options->jsonPath);
            }

            return $this->renderReports($output, $covData, $options);
        }

        if ($this->collector === null) {
            return null;
        }

        $this->collector->stop();
        $covData = $this->collector->filter($options->srcPath);

        return $this->renderReports($output, $covData, $options);
    }

    /**
     * Merges partial collector state files produced by parallel workers into
     * the active per-example collector, removing each file once applied.
     *
     * @param array<int, string> $paths partial state file paths written by workers
     */
    public function mergePartials(array $paths): void
    {
        $collector = CoverageRegistry::collector();

        if ($collector === null) {
            return;
        }

        foreach ($paths as $path) {
            if (!is_file($path)) {
                continue;
            }

            $state = json_decode((string) file_get_contents($path), true);

            if (is_array($state)) {
                $collector->applyState($state);
            }

            unlink($path);
        }
    }

    /**
     * Dumps the raw collector state as JSON for the parent process to merge.
     *
     * @param PerExampleCollector $collector the collector holding this worker's coverage
     * @param string $partialPath the file path to write the state to
     */
    private function writePartial(PerExampleCollector $collector, string $partialPath): void
    {
        $dir = dirname($partialPath);

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents($partialPath, json_encode($collector->toArray()));
    }

    /**
     * Builds and writes the JSON coverage report from the per-example collection.
     *
     * @param Output $output the console output
     * @param PerExampleCollector $collector the collector holding per-example coverage
     * @param string $srcPath the source directory to scope the report to
     * @param string $jsonPath the file path to write the JSON report to
     */
    private function renderJson(Output $output, PerExampleCollector $collector, string $srcPath, string $jsonPath): void
    {
        $realSrcPath = realpath($srcPath);
        $projectRoot = getcwd();

        $report = (new JsonReportBuilder(new RealFilesystem()))->build(
            $collector,
            $realSrcPath !== false ? $realSrcPath : $srcPath,
            $projectRoot !== false ? $projectRoot : '.',
        );
        (new JsonReport())->render($report['tests'], $report['sources'], $jsonPath);

        $output->writeln("  JSON coverage report: $jsonPath");
    }

    /**
     * Renders the text, Clover and HTML reports and measures the run against
     * the minimum coverage threshold.
     *
     * @param Output $output the console output
     * @param array<string, array<int, int>> $covData filtered coverage data, relative file paths mapped to line hits
     * @param CoverageOptions $options the requested reports and their destinations
     * @return CoverageVerdict what the run covered, and whether that was enough
     * @throws DOMException
     */
    private function renderReports(Output $output, array $covData, CoverageOptions $options): CoverageVerdict
    {
        if ($options->showText) {
            (new TextReport())->render($covData, $output);
        }

        if ($options->cloverPath !== null) {
            (new CloverReport())->render($covData, $options->cloverPath);
            $output->writeln("  Clover report: {$options->cloverPath}");
        }

        if ($options->htmlPath !== null) {
            (new HtmlReport())->render($covData, $options->htmlPath);
            $output->writeln("  HTML report: {$options->htmlPath}/index.html");
        }

        $totalCovered = 0;
        $totalExecutable = 0;

        foreach ($covData as $lines) {
            $counts = CoverageCollector::countLines($lines);
            $totalCovered += $counts['covered'];
            $totalExecutable += $counts['executable'];
        }

        $verdict = new CoverageVerdict(
            $totalExecutable > 0 ? ($totalCovered / $totalExecutable) * 100 : 0.0,
            $options->coverageMin !== null ? (float) $options->coverageMin : null,
        );

        if ($verdict->required !== null) {
            $output->writeln($verdict->met()
                ? sprintf('<fg=green>Code coverage %.1f%% meets the required %.1f%%</>', $verdict->percent, $verdict->required)
                : sprintf('<fg=red>Code coverage %.1f%% is below the required %.1f%%</>', $verdict->percent, $verdict->required));
        }

        return $verdict;
    }
}
