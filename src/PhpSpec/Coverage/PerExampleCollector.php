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
 * Collects line coverage around each example and scenario, so only what they
 * run counts. When crediting, it collects at the end of each one and keeps
 * which test ran each line, naming tests from the current spec file and
 * context titles: "spec/App/Calculator.spec.php::Calculator > adds two numbers".
 * Otherwise it pauses the driver between them and collects once, at finish().
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

    /** @var int how many examples are open, an example run by another example being part of it */
    private int $open = 0;

    /** @var bool whether the paused driver holds what ran since it was last collected */
    private bool $pending = false;

    /**
     * @param bool $crediting whether to keep which test ran each line, or only what ran
     */
    public function __construct(
        private readonly CoverageDriver $driver,
        private readonly bool $crediting = true,
    ) {}

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
        if ($this->open > 0) {
            return;
        }

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
        if ($this->open > 0) {
            return;
        }

        $this->contextTitles[] = $title;
    }

    /**
     * Pops the innermost context title off the current title stack.
     */
    public function popContext(): void
    {
        if ($this->open > 0) {
            return;
        }

        array_pop($this->contextTitles);
    }

    /**
     * Starts or resumes collection for a single example, resetting the peak
     * memory watermark and recording the start time.
     */
    public function beginExample(): void
    {
        if ($this->open++ > 0) {
            return;
        }

        memory_reset_peak_usage();
        $this->startTime = hrtime(true);
        $this->driver->start();
    }

    /**
     * Ends collection for the example. When crediting, what ran goes into the
     * aggregate and every executed line is credited to the example named by
     * the current spec path, context titles and title, with the example's
     * duration and peak memory usage; otherwise the driver is paused until the
     * next example or finish().
     *
     * @param string $title the example title
     */
    public function endExample(string $title): void
    {
        if (--$this->open > 0) {
            return;
        }

        if (!$this->crediting) {
            $this->driver->pause();
            $this->pending = true;

            return;
        }

        $data = $this->driver->stop();
        $this->aggregated($data);
        $this->credit($this->testId($title), $data);
    }

    /**
     * Collects what the examples ran since the driver was last collected,
     * when it was paused rather than collected after each one.
     */
    public function finish(): void
    {
        if (!$this->pending) {
            return;
        }

        $this->pending = false;
        $this->aggregated($this->driver->stop());
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
     * Returns line hit data merged across all example cycles, which the text,
     * Clover and HTML reports render.
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
     * Exports the collector state for transfer between processes. Each line
     * names its tests by their place in the test list: a test's name, repeated
     * on every line it ran, would make the state many times the size.
     *
     * @return array{tests: array<string, array{time: float, memory: int, spec_file: string}>, lines: array<string, array<int, list<int>>>, aggregate: array<string, array<int, int>>}
     */
    public function toArray(): array
    {
        $places = array_flip(array_keys($this->tests));
        $lines = [];

        foreach ($this->lines as $file => $fileLines) {
            foreach ($fileLines as $lineNumber => $testIds) {
                $lines[$file][$lineNumber] = array_map(static fn(string $testId): int => $places[$testId], array_keys($testIds));
            }
        }

        return [
            'tests' => $this->tests,
            'lines' => $lines,
            'aggregate' => $this->aggregate,
        ];
    }

    /**
     * Merges state exported by another collector (e.g. from a parallel worker)
     * into this one. Line attributions are unioned without duplicates and
     * aggregate hits are merged keeping the highest value per line.
     *
     * @param array{tests?: array<string, array{time: float, memory: int, spec_file: string}>, lines?: array<string, array<int, list<int>>>, aggregate?: array<string, array<int, int>>} $state state produced by toArray()
     */
    public function applyState(array $state): void
    {
        $testIds = array_map('strval', array_keys($state['tests'] ?? []));

        foreach ($state['tests'] ?? [] as $testId => $test) {
            $this->tests[(string) $testId] = $test;
        }

        foreach ($state['lines'] ?? [] as $file => $lines) {
            foreach ($lines as $lineNumber => $places) {
                foreach ($places as $place) {
                    $this->lines[$file][$lineNumber][$testIds[$place]] = true;
                }
            }
        }

        $this->aggregated($state['aggregate'] ?? []);
    }

    /**
     * Merges line hits into the aggregate, keeping the highest value per line.
     *
     * @param array<string, array<int, int>> $data file path => line => hit value
     */
    private function aggregated(array $data): void
    {
        foreach ($data as $file => $lines) {
            foreach ($lines as $lineNumber => $hit) {
                $this->aggregate[$file][$lineNumber] = max($this->aggregate[$file][$lineNumber] ?? $hit, $hit);
            }
        }
    }

    /**
     * Records the test as having run every line it hit, with its duration and
     * peak memory.
     *
     * @param array<string, array<int, int>> $data what the driver collected while the test ran
     */
    private function credit(string $testId, array $data): void
    {
        $this->tests[$testId] = [
            'time' => (hrtime(true) - $this->startTime) / 1e9,
            'memory' => memory_get_peak_usage(),
            'spec_file' => (string) $this->specPath,
        ];

        foreach ($data as $file => $lines) {
            foreach ($lines as $lineNumber => $hit) {
                if ($hit >= 1) {
                    $this->lines[$file][$lineNumber][$testId] = true;
                }
            }
        }
    }
}
