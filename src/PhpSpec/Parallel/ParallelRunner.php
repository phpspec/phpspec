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

use PhpSpec\Results;
use PhpSpec\StopConditions;

/**
 * @internal
 * Runs spec files in parallel using child processes managed by Fibers for non-blocking I/O.
 *
 * Each worker process runs a partition of spec files via `phpspec run -f wire`
 * and reports each result back whole, on its own line, as it completes.
 */
final class ParallelRunner
{
    private readonly int $workers;
    private readonly string $phpspecBin;

    /** @var array<int, string> partial coverage state files assigned to workers */
    private array $coveragePartials = [];

    /** @var list<WorkerProcess> */
    private array $processes = [];

    /**
     * @param string[] $paths spec file paths to run
     * @param int|null $workers number of worker processes (null = auto-detect CPU count)
     * @param StopConditions $stop conditions under which to halt early
     * @param string|null $phpspecBin absolute path to phpspec binary (null = auto-detect)
     * @param string|null $coveragePartialDir directory for workers to dump raw coverage state to, or null to run without coverage
     * @param list<string> $childOptions the options the run was started with that set up the project, forwarded to every worker
     * @param string|null $filter the title filter to forward to workers
     * @param string|null $tags the tag expression to forward to workers
     * @param list<string> $phpOptions the php options every worker runs under, which switch its coverage engine on or off, after the settings it takes from this process
     */
    public function __construct(
        private readonly array $paths,
        ?int $workers = null,
        private readonly StopConditions $stop = new StopConditions(),
        ?string $phpspecBin = null,
        private readonly ?string $coveragePartialDir = null,
        private readonly array $childOptions = [],
        private readonly ?string $filter = null,
        private readonly ?string $tags = null,
        private readonly array $phpOptions = [],
    ) {
        $this->workers = max(1, $workers ?? self::detectCpuCount());
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
        if (empty($this->paths)) {
            return;
        }

        $parent = new PhpSetup($this->settings(ini_get_all(null, false)), get_loaded_extensions(), get_loaded_extensions(true));
        $fresh = $this->freshSetup();
        $phpOptions = [...($fresh === null ? [] : $parent->over($fresh)), ...$this->phpOptions];
        $workerCount = min($this->workers, count($this->paths));
        $partitions = array_fill(0, $workerCount, []);
        foreach ($this->paths as $i => $path) {
            $partitions[$i % $workerCount][] = $path;
        }

        /** @var WorkerProcess[] $processes */
        $processes = [];
        $fibers = [];

        foreach ($partitions as $i => $partition) {
            $coveragePartial = null;

            if ($this->coveragePartialDir !== null) {
                $coveragePartial = $this->coveragePartialDir . '/partial-' . $i . '.json';
                $this->coveragePartials[] = $coveragePartial;
            }

            $process = new WorkerProcess($partition, $this->phpspecBin, $coveragePartial, $this->childOptions, $this->stop, $this->filter, $this->tags, $phpOptions);
            $processes[] = $process;
            $this->processes[] = $process;

            $fiber = new \Fiber(function () use ($process) {
                $process->start();
                while ($process->isRunning()) {
                    $process->read();
                    \Fiber::suspend();
                }
                $process->read();
                return $process->getResults();
            });
            $fiber->start();
            $fibers[] = $fiber;
        }

        while (!empty($fibers)) {
            foreach ($fibers as $key => $fiber) {
                if ($fiber->isSuspended()) {
                    $fiber->resume();
                }

                $results = $fiber->isTerminated() ? $fiber->getReturn() : $processes[$key]->takeResults();
                foreach ($results as $result) {
                    yield $result;
                    if ($this->stop->metBy($result)) {
                        foreach ($processes as $p) {
                            $p->terminate();
                        }
                        return;
                    }
                }

                if ($fiber->isTerminated()) {
                    unset($fibers[$key]);
                }
            }
            if (!empty($fibers)) {
                usleep(10000);
            }
        }
    }

    /**
     * Detects the number of CPU cores on the current system.
     *
     * @return int the number of available CPU cores (minimum 1)
     */
    public static function detectCpuCount(): int
    {
        if (PHP_OS_FAMILY === 'Darwin') {
            return max(1, (int) shell_exec('sysctl -n hw.ncpu'));
        }
        if (PHP_OS_FAMILY === 'Windows') {
            return max(1, (int) (getenv('NUMBER_OF_PROCESSORS') ?: 4));
        }

        return max(1, (int) shell_exec('nproc'));
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

    /**
     * How a php started afresh on the same binary is set up, asked of one, or
     * null when it cannot be asked.
     */
    private function freshSetup(): ?PhpSetup
    {
        $process = proc_open([PHP_BINARY, '-r', 'echo json_encode([ini_get_all(null, false), get_loaded_extensions(), get_loaded_extensions(true)]);'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

        if (!is_resource($process)) {
            return null;
        }

        $printed = (string) stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
        $setup = json_decode($printed, true);

        if (!is_array($setup) || !is_array($setup[1] ?? null) || !is_array($setup[2] ?? null)) {
            return null;
        }

        return new PhpSetup($this->settings($setup[0] ?? null), $this->names($setup[1]), $this->names($setup[2]));
    }

    /**
     * @param array<mixed> $names
     * @return list<string>
     */
    private function names(array $names): array
    {
        return array_values(array_filter($names, 'is_string'));
    }

    /**
     * @return array<string, string|null>
     */
    private function settings(mixed $all): array
    {
        $settings = [];

        foreach (is_array($all) ? $all : [] as $name => $value) {
            if (is_string($name) && (is_string($value) || $value === null)) {
                $settings[$name] = $value;
            }
        }

        return $settings;
    }
}
