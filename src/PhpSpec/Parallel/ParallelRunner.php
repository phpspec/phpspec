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

namespace PhpSpec\Parallel;

use PhpSpec\ByReturnValue;
use PhpSpec\Process\ChildPhp;
use PhpSpec\Result\FeatureResult;
use PhpSpec\Result\SpecificationResult;
use PhpSpec\Results;
use PhpSpec\SeededShuffle;
use PhpSpec\StopConditions;

/**
 * @internal
 * Runs spec files in parallel in child processes, waiting on all of their output at once.
 *
 * Each worker process is handed one file at a time, runs it through
 * `phpspec run --queue -f wire` and reports each result back whole, on its
 * own line, as it completes. A worker that ran its file is handed the next,
 * so a slow file holds no other back.
 */
final class ParallelRunner
{
    private readonly int $workers;
    private readonly string $phpspecBin;

    /** @var array<int, string> partial coverage state files assigned to workers */
    private array $coveragePartials = [];

    /** @var list<WorkerProcess> */
    private array $processes = [];

    /** @var list<string> the files no worker has been handed yet */
    private array $queue = [];

    /**
     * @param array<string, list<string>> $targets each file to run, to the targets its worker is given for it
     * @param int|null $workers number of worker processes (null = auto-detect CPU count)
     * @param StopConditions $stop conditions under which to halt early
     * @param string|null $phpspecBin absolute path to phpspec binary (null = auto-detect)
     * @param string|null $coveragePartialDir directory for workers to dump raw coverage state to, or null to run without coverage
     * @param list<string> $childOptions the options the run was started with that set up the project, forwarded to every worker
     * @param string|null $filter the title filter to forward to workers
     * @param string|null $tags the tag expression to forward to workers
     * @param list<string> $phpOptions the php options every worker runs under, which switch its coverage engine on or off, after the settings it takes from this process
     * @param int|null $seed the seed of a random run, which orders the files as they are handed to the workers, or null to hand the features over first, the largest first, then the specs as found
     * @param bool $perExample whether the workers credit each covered line to the example that ran it, which only the JSON report reads
     */
    public function __construct(
        private readonly array $targets,
        ?int $workers = null,
        private readonly StopConditions $stop = new StopConditions(),
        ?string $phpspecBin = null,
        private readonly ?string $coveragePartialDir = null,
        private readonly array $childOptions = [],
        private readonly ?string $filter = null,
        private readonly ?string $tags = null,
        private readonly array $phpOptions = [],
        private readonly ?int $seed = null,
        private readonly bool $perExample = false,
    ) {
        $this->workers = max(1, $workers ?? (new CpuCount())->count());
        $this->phpspecBin = $phpspecBin ?? self::findPhpspecBin();
    }

    /**
     * Returns the partial coverage state files assigned to workers, for the
     * parent process to merge once the stream has been fully consumed.
     *
     * @return array<int, string> partial state file paths
     */
    public function getCoveragePartials(): array
    {
        return $this->coveragePartials;
    }

    /**
     * Why the coverage of each worker that never handed it over was lost, once
     * the stream has been fully consumed.
     *
     * @return list<string>
     */
    public function lostCoverage(): array
    {
        return array_values(array_filter(array_map(static fn(WorkerProcess $process): ?string => $process->lostCoverage(), $this->processes)));
    }

    /**
     * Yields results as they complete from worker processes.
     *
     * @return \Generator<Results>
     */
    public function stream(): \Generator
    {
        if ($this->targets === []) {
            return;
        }

        $phpOptions = [...(new ChildPhp())->options(), ...$this->phpOptions];
        // A random run hands the files over in the order its seed gives. Any
        // other hands the features over first, the largest first, since the
        // scenarios a feature holds make it long, so the longest is not the
        // last to start; then the specs as they were found, since a spec
        // file's size says nothing of what its examples do.
        $this->queue = $this->seed === null ? $this->featuresFirst(array_keys($this->targets)) : (new SeededShuffle($this->seed))->of(array_keys($this->targets));

        foreach (range(1, min($this->workers, count($this->queue))) as $number) {
            $process = new WorkerProcess($this->phpspecBin, $this->coveragePartial($number), $this->childOptions, $this->stop, $this->filter, $this->tags, $phpOptions, $number, $this->perExample);
            $process->start();
            $this->hand($process);
            $this->processes[] = $process;
        }

        foreach ($this->results() as $result) {
            yield $result;

            if ($this->stop->metBy($result)) {
                foreach ($this->processes as $stopped) {
                    $stopped->terminate();
                }

                return;
            }
        }
    }

    /**
     * What the workers report as they run the files they are handed, until
     * every one is done.
     *
     * @return \Generator<SpecificationResult|FeatureResult>
     */
    private function results(): \Generator
    {
        $running = $this->processes;
        $last = null;

        while ($running !== []) {
            $this->awaitOutput($running);

            foreach ($running as $key => $process) {
                $process->read();
                $done = !$process->isRunning();
                $results = $done ? $process->getResults() : $process->takeResults();

                // Handed the next file before its results are reported, so a
                // worker that ran its file is not kept waiting on the report.
                if ($done) {
                    unset($running[$key]);
                    $last = $process;
                } elseif ($process->wantsWork()) {
                    $this->hand($process);
                }

                yield from $results;
            }
        }

        // Every worker died with files left to hand over: the last to go says why.
        if ($last !== null) {
            yield from $last->leftBehind($this->queue);
        }
    }

    /**
     * Hands the worker the next file, or tells it none is left.
     */
    private function hand(WorkerProcess $process): void
    {
        $file = array_shift($this->queue);

        if ($file === null) {
            $process->finish();

            return;
        }

        $process->give($file, $this->targets[$file]);
    }

    /**
     * @param list<string> $files
     * @return list<string>
     */
    private function featuresFirst(array $files): array
    {
        $features = array_values(array_filter($files, static fn(string $file): bool => str_ends_with($file, '.feature')));
        $sizes = array_combine($features, array_map(static fn(string $file): int => is_file($file) ? (int) filesize($file) : 0, $features));
        usort($features, static fn(string $one, string $other): int => $sizes[$other] <=> $sizes[$one]);

        return [...$features, ...array_values(array_diff($files, $features))];
    }

    private function coveragePartial(int $worker): ?string
    {
        if ($this->coveragePartialDir === null) {
            return null;
        }

        $partial = $this->coveragePartialDir . '/partial-' . $worker . '.json';
        $this->coveragePartials[] = $partial;

        return $partial;
    }

    /**
     * Waits until any worker has something to say or has closed a pipe, and no
     * longer than a tenth of a second, so a worker that exits without writing
     * is still noticed.
     *
     * @param array<int, WorkerProcess> $running
     */
    private function awaitOutput(array $running): void
    {
        $read = array_merge(...array_values(array_map(static fn(WorkerProcess $process): array => $process->pipes(), $running)));

        if ($read === []) {
            usleep(1000);

            return;
        }

        $write = null;
        $except = null;

        // A signal arriving mid-wait makes it warn and answer false: look again.
        (new ByReturnValue())->call(static function () use (&$read, &$write, &$except): int|false {
            return stream_select($read, $write, $except, 0, 100000);
        });
    }

    /**
     * Resolves the absolute path to the phpspec binary, so a subprocess can
     * re-invoke the same phpspec (used by parallel workers and pair mode).
     */
    public static function findPhpspecBin(): string
    {
        // This file is at src/PhpSpec/Parallel/ParallelRunner.php
        // bin/phpspec is 3 directories up
        $bin = dirname(__DIR__, 3) . '/bin/phpspec';
        if (file_exists($bin)) {
            $resolved = realpath($bin);
            return $resolved !== false ? $resolved : $bin;
        }

        $argv0 = $_SERVER['argv'][0] ?? 'phpspec';
        $resolved = realpath($argv0);
        return $resolved !== false ? $resolved : $argv0;
    }
}
