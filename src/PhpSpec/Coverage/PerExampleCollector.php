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

/**
 * @internal
 * Collects line coverage attributed to individual examples by cycling the
 * coverage driver around each example run. Tracks the current spec file and
 * context titles to build stable test identifiers of the form
 * "spec/App/Calculator.spec.php::Calculator > adds two numbers".
 */
final class PerExampleCollector
{
    /** @var array<string, array<int, array<string, true>>> file path => line number => set of test identifiers */
    private array $lines = [];

    /** @var array<string, array<int, int>> line hit data merged across all cycles, in raw driver shape */
    private array $aggregate = [];

    /** @var array<string, array{time: float, memory: int, spec_file: string}> per-test metadata keyed by test identifier */
    private array $tests = [];

    private ?string $specPath = null;

    /** @var array<int, string> stack of context titles for the currently running spec */
    private array $contextTitles = [];

    /** @var float example start time from hrtime(), in nanoseconds */
    private float $startTime = 0.0;

    public function __construct(private readonly CoverageDriver $driver) {}

    /**
     * Records the spec file that examples are about to run from.
     * A leading "./" is stripped and backslashes are normalised to forward
     * slashes, so test identifiers use the same clean relative paths on
     * every platform.
     *
     * @param string $path the spec file path
     */
    public function beginSpec(string $path): void
    {
        $path = str_replace('\\', '/', $path);
        $this->specPath = str_starts_with($path, './') ? substr($path, 2) : $path;
        $this->contextTitles = [];
    }

    /**
     * Pushes a describe/context title onto the current title stack.
     *
     * @param string $title the context title
     */
    public function pushContext(string $title): void
    {
        $this->contextTitles[] = $title;
    }

    /**
     * Pops the innermost context title off the current title stack.
     */
    public function popContext(): void
    {
        array_pop($this->contextTitles);
    }

    /**
     * Starts a coverage collection cycle for a single example, resetting the
     * peak memory watermark and recording the start time.
     */
    public function beginExample(): void
    {
        memory_reset_peak_usage();
        $this->startTime = hrtime(true);
        $this->driver->start();
    }

    /**
     * Stops the current collection cycle, attributes every executed line
     * to the example identified by the current spec path, context titles and
     * title, and records the example's duration and peak memory usage.
     *
     * @param string $title the example title
     */
    public function endExample(string $title): void
    {
        $data = $this->driver->stop();
        $elapsed = (hrtime(true) - $this->startTime) / 1e9;
        $testId = $this->testId($title);

        $this->tests[$testId] = [
            'time' => $elapsed,
            'memory' => memory_get_peak_usage(),
            'spec_file' => (string) $this->specPath,
        ];

        foreach ($data as $file => $lines) {
            foreach ($lines as $lineNumber => $hit) {
                if ($hit >= 1) {
                    $this->lines[$file][$lineNumber][$testId] = true;
                }
                $this->aggregate[$file][$lineNumber] = max($this->aggregate[$file][$lineNumber] ?? $hit, $hit);
            }
        }
    }

    /**
     * Returns executed lines mapped to the test identifiers that covered them.
     *
     * @return array<string, array<int, array<int, string>>> file path => line number => test identifiers
     */
    public function getLines(): array
    {
        $result = [];
        foreach ($this->lines as $file => $lines) {
            foreach ($lines as $lineNumber => $testIds) {
                $result[$file][$lineNumber] = array_keys($testIds);
            }
        }

        return $result;
    }

    /**
     * Returns line hit data merged across all example cycles, in the same
     * shape as a whole-suite collection, so the text, Clover and HTML reports
     * can be rendered from a per-example run.
     *
     * @return array<string, array<int, int>> file paths mapped to line-level hit counts
     */
    public function getAggregate(): array
    {
        return $this->aggregate;
    }

    /**
     * Builds the stable test identifier for the currently running example.
     *
     * @param string $title the example title
     * @return string identifier such as "spec/App/Calculator.spec.php::Calculator > adds two numbers"
     */
    private function testId(string $title): string
    {
        $titles = [...$this->contextTitles, $title];

        return $this->specPath . '::' . implode(' > ', $titles);
    }

    /**
     * Returns per-test timing, memory and spec file metadata.
     *
     * @return array<string, array{time: float, memory: int, spec_file: string}> metadata keyed by test identifier
     */
    public function getTests(): array
    {
        return $this->tests;
    }

    /**
     * Exports the collector state for transfer between processes.
     *
     * @return array{tests: array<string, array{time: float, memory: int, spec_file: string}>, lines: array<string, array<int, array<int, string>>>, aggregate: array<string, array<int, int>>}
     */
    public function toArray(): array
    {
        return [
            'tests' => $this->tests,
            'lines' => $this->getLines(),
            'aggregate' => $this->aggregate,
        ];
    }

    /**
     * Merges state exported by another collector (e.g. from a parallel worker)
     * into this one. Line attributions are unioned without duplicates and
     * aggregate hits are merged keeping the highest value per line.
     *
     * @param array{tests?: array<string, array{time: float, memory: int, spec_file: string}>, lines?: array<string, array<int, array<int, string>>>, aggregate?: array<string, array<int, int>>} $state state produced by toArray()
     */
    public function applyState(array $state): void
    {
        foreach ($state['tests'] ?? [] as $testId => $test) {
            $this->tests[$testId] = $test;
        }

        foreach ($state['lines'] ?? [] as $file => $lines) {
            foreach ($lines as $lineNumber => $testIds) {
                foreach ($testIds as $testId) {
                    $this->lines[$file][$lineNumber][$testId] = true;
                }
            }
        }

        foreach ($state['aggregate'] ?? [] as $file => $lines) {
            foreach ($lines as $lineNumber => $hit) {
                $this->aggregate[$file][$lineNumber] = max($this->aggregate[$file][$lineNumber] ?? $hit, $hit);
            }
        }
    }
}
