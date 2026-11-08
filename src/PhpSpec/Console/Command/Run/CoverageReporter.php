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
use PhpSpec\Coverage\CoverageAdapter;
use PhpSpec\Coverage\CoverageData;
use PhpSpec\Coverage\CoverageEngines;
use PhpSpec\Coverage\CoverageOptions;
use PhpSpec\Coverage\CoverageRefusal;
use PhpSpec\Coverage\CoverageRegistry;
use PhpSpec\Coverage\CoverageVerdict;
use PhpSpec\Coverage\Exclusions;
use PhpSpec\Coverage\HtmlReport;
use PhpSpec\Coverage\JsonReport;
use PhpSpec\Coverage\JsonReportBuilder;
use PhpSpec\Coverage\Percentage;
use PhpSpec\Coverage\PerExampleCollector;
use PhpSpec\Coverage\SourceFiles;
use PhpSpec\Coverage\TextReport;
use PhpSpec\ProjectRoot;
use PhpSpec\RealFilesystem;
use Symfony\Component\Console\Output\OutputInterface as Output;

/**
 * @internal
 * Manages the complete code coverage lifecycle: the choice of engine,
 * collection, report rendering (text, Clover XML, HTML, JSON), and minimum
 * threshold enforcement. The runner cycles collection around each example and
 * scenario, so only what they run counts; per example, it also credits each
 * line to the example that ran it.
 */
final class CoverageReporter
{
    private ?CoverageAdapter $adapter = null;

    /** @var array<string, array<int, int>> the lines the last run exercised, by absolute path */
    private array $hits = [];

    /** @var list<string> why part of the run's coverage never arrived */
    private array $lost = [];

    private readonly Exclusions $exclusions;

    /**
     * @param Exclusions|null $exclusions what the reports leave out; interfaces only when null
     */
    public function __construct(
        private readonly CoverageEngines $engines,
        ?Exclusions $exclusions = null,
    ) {
        $this->exclusions = $exclusions ?? new Exclusions([], new RealFilesystem(), (string) getcwd());
    }

    /**
     * The lines this run exercised, as the engine reports them: covered,
     * executable but never reached, or not executable at all. Kept from the
     * last report so guard asks the same data the percentage was worked out
     * from, rather than collecting its own and risking a different answer.
     *
     * @return array<string, array<int, int>> file path => line => hit value
     */
    public function hits(): array
    {
        return $this->hits;
    }

    /**
     * The engine collecting, once started.
     */
    public function adapter(): ?CoverageAdapter
    {
        return $this->adapter;
    }

    /**
     * Picks the engine and activates a collector in the CoverageRegistry,
     * which the runner cycles around each example and scenario.
     *
     * @param list<string> $traced the directories the run reports on
     * @param bool $perExample whether to credit each line to the example that ran it
     * @return CoverageRefusal|null null once collection started, or why it could not
     */
    public function start(array $traced, bool $perExample = false): ?CoverageRefusal
    {
        $adapter = $this->engines->inThisProcess($traced);

        if ($adapter instanceof CoverageRefusal) {
            return $adapter;
        }

        $this->adapter = $adapter;
        CoverageRegistry::activate(new PerExampleCollector($adapter->driver($traced), crediting: $perExample));

        return null;
    }

    /**
     * Ends collection and renders all requested coverage reports.
     *
     * When a partial path is set (the parallel worker case), the raw collector
     * state is dumped there instead of rendering any report.
     *
     * @param Output $output the console output
     * @param CoverageOptions $options the requested reports and their destinations
     * @return CoverageVerdict|CoverageRefusal|null what the run covered, why it cannot say
     *                                              when part of its coverage never arrived,
     *                                              or null when nothing was collected or the
     *                                              state was only dumped for a worker
     * @throws DOMException
     */
    public function report(Output $output, CoverageOptions $options): CoverageVerdict|CoverageRefusal|null
    {
        $collector = CoverageRegistry::collector();

        if ($collector === null) {
            return null;
        }

        CoverageRegistry::reset();
        $collector->finish();

        if ($this->lost !== []) {
            return new CoverageRefusal(implode("\n", $this->lost));
        }

        if ($options->partialPath !== null) {
            $this->writePartial($collector, $options->partialPath);

            return null;
        }

        // Kept unfiltered and absolute: the reports want the source
        // directory's view, guard wants the project's, and guard bounds the
        // paths itself.
        $this->hits = $collector->getAggregate();
        $covData = $this->reported($options->sources);

        if ($options->jsonPath !== null) {
            $this->renderJson($output, $collector, $options->sources, $options->jsonPath);
        }

        return $this->renderReports($output, $covData, $options);
    }

    /**
     * The hits the reports cover: the source directories', without what
     * coverage leaves out, named as {@see CoverageData::filterData()} names
     * them. Narrowed first, so no file outside them is read for what it
     * leaves out.
     *
     * @param list<string> $sources
     * @return array<string, array<int, int>>
     */
    private function reported(array $sources): array
    {
        $roots = array_map(ProjectRoot::at(...), $sources);
        $inSource = array_filter($this->hits, static fn(string $file): bool => array_filter($roots, static fn(ProjectRoot $root): bool => $root->holds($file)) !== [], ARRAY_FILTER_USE_KEY);

        return CoverageData::filterData($this->exclusions->appliedTo($inSource), ...$sources);
    }

    /**
     * Merges partial collector state files produced by parallel workers into
     * the active per-example collector, removing each file once applied. What
     * never arrived, or arrived unreadable, leaves the run nothing to report.
     *
     * @param array<int, string> $paths partial state file paths written by workers
     * @param list<string> $lost why the coverage of each worker that never handed it over was lost
     */
    public function mergePartials(array $paths, array $lost = []): void
    {
        $this->lost = [...$this->lost, ...$lost];
        $collector = CoverageRegistry::collector();

        foreach ($paths as $path) {
            if (!is_file($path)) {
                continue;
            }

            $state = json_decode((string) file_get_contents($path), true);
            unlink($path);

            if (!is_array($state)) {
                $this->lost[] = 'Coverage is incomplete: a worker handed over coverage that could not be read.';

                continue;
            }

            $collector?->applyState($state);
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
     * @param list<string> $sources the source directories to scope the report to
     * @param string $jsonPath the file path to write the JSON report to
     */
    private function renderJson(Output $output, PerExampleCollector $collector, array $sources, string $jsonPath): void
    {
        $projectRoot = getcwd();

        $report = (new JsonReportBuilder(new RealFilesystem(), $this->exclusions))->build(
            $collector,
            array_map(static fn(string $source): string => realpath($source) ?: $source, $sources),
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
            (new HtmlReport($options->sources))->render($covData, $options->htmlPath);
            $output->writeln("  HTML report: {$options->htmlPath}/index.html");
        }

        $totalCovered = 0;
        $totalExecutable = 0;

        foreach ($covData as $lines) {
            $counts = CoverageData::countLines($lines);
            $totalCovered += $counts['covered'];
            $totalExecutable += $counts['executable'];
        }

        if ($options->coverageMin === null) {
            return new CoverageVerdict(new Percentage($totalCovered, $totalExecutable));
        }

        $verdict = new CoverageVerdict(
            new Percentage($totalCovered, $totalExecutable),
            (float) $options->coverageMin,
            (new SourceFiles(new RealFilesystem(), array_map(static fn(string $source): string => realpath($source) ?: $source, $options->sources), ProjectRoot::here()))->unrecorded($this->hits, $this->exclusions),
        );
        $this->printJudgement($output, $verdict, $options->coverageMin);

        return $verdict;
    }

    private function printJudgement(Output $output, CoverageVerdict $verdict, string $required): void
    {
        if ($verdict->unrecorded !== []) {
            $output->writeln(sprintf('<fg=red>Cannot enforce --coverage-min=%s: coverage is incomplete.</>', $required));

            foreach ($verdict->unrecorded as $file) {
                $output->writeln(sprintf('<fg=red>No coverage was recorded for %s during an example or scenario.</>', $file));
            }

            return;
        }

        $output->writeln($verdict->met()
            ? sprintf('<fg=green>Code coverage %s%% meets the required %s%%</>', $verdict->shown(), $verdict->shownRequired())
            : sprintf('<fg=red>Code coverage %s%% is below the required %s%%</>', $verdict->shown(), $verdict->shownRequired()));
    }
}
