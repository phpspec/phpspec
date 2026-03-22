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

use PhpSpec\Result\ExampleResult;
use PhpSpec\Result\StepResult;
use PhpSpec\Results;
use PhpSpec\StopConditions;

/**
 * Runs spec files in parallel using child processes managed by Fibers for non-blocking I/O.
 *
 * Each worker process runs a partition of spec files via `phpspec run -f junit`,
 * and results are parsed from the JUnit XML output.
 */
final class ParallelRunner
{
    private readonly int $workers;
    private readonly string $phpspecBin;

    /**
     * @param string[] $paths spec file paths to run
     * @param int|null $workers number of worker processes (null = auto-detect CPU count)
     * @param StopConditions $stop conditions under which to halt early
     * @param string|null $phpspecBin absolute path to phpspec binary (null = auto-detect)
     */
    public function __construct(
        private readonly array $paths,
        ?int $workers = null,
        private readonly StopConditions $stop = new StopConditions(),
        ?string $phpspecBin = null,
    ) {
        $this->workers = max(1, $workers ?? self::detectCpuCount());
        $this->phpspecBin = $phpspecBin ?? self::findPhpspecBin();
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

        $workerCount = min($this->workers, count($this->paths));
        $partitions = array_fill(0, $workerCount, []);
        foreach ($this->paths as $i => $path) {
            $partitions[$i % $workerCount][] = $path;
        }

        /** @var WorkerProcess[] $processes */
        $processes = [];
        $fibers = [];

        foreach ($partitions as $partition) {
            $process = new WorkerProcess($partition, $this->phpspecBin);
            $processes[] = $process;

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
                if ($fiber->isTerminated()) {
                    $results = $fiber->getReturn();
                    foreach ($results as $result) {
                        yield $result;
                        if ($this->stop->any() && self::shouldStop($result, $this->stop)) {
                            foreach ($processes as $p) {
                                $p->terminate();
                            }
                            return;
                        }
                    }
                    unset($fibers[$key]);
                } elseif ($fiber->isSuspended()) {
                    $fiber->resume();
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
     * Resolves the absolute path to the phpspec binary.
     */
    private static function findPhpspecBin(): string
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
     * Checks whether any result in the tree triggers a configured stop condition.
     */
    private static function shouldStop(Results $results, StopConditions $stop): bool
    {
        foreach ($results->getResults() as $result) {
            if ($result instanceof ExampleResult) {
                if ($stop->onFailure && ($result->isFailure() || $result->isError())) {
                    return true;
                }
                if ($stop->onError && $result->isError()) {
                    return true;
                }
                if ($stop->onWarning && $result->hasWarnings()) {
                    return true;
                }
                if ($stop->onDeprecation && $result->hasDeprecations()) {
                    return true;
                }
                if ($stop->onNotice && $result->hasNotices()) {
                    return true;
                }
                if ($stop->onSkipped && $result->isSkipped()) {
                    return true;
                }
            } elseif ($result instanceof StepResult) {
                if ($stop->onFailure && $result->isFailure()) {
                    return true;
                }
            } elseif ($result instanceof Results) {
                if (self::shouldStop($result, $stop)) {
                    return true;
                }
            }
        }
        return false;
    }
}
