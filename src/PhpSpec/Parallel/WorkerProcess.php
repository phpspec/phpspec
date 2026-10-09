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
use PhpSpec\Result\FeatureResult;
use PhpSpec\Result\SpecificationResult;
use PhpSpec\Specification\ExampleError;
use PhpSpec\StopConditions;

/**
 * @internal
 * Wraps a single child process that runs phpspec on a set of spec files and
 * reports each result back over the wire, one line per spec or feature.
 */
final class WorkerProcess
{
    /** @var resource|null */
    private $process = null;

    /** @var array<int, resource> */
    private array $pipes = [];

    private string $stdout = '';
    private string $stderr = '';
    private ?int $exitCode = null;

    private readonly Wire $wire;

    /** @var list<string> the files the worker has reported so far */
    private array $reported = [];

    private bool $ended = false;

    /**
     * @param string[] $paths spec file paths to run in this worker
     * @param string $phpspecBin absolute path to the phpspec binary
     * @param string|null $coveragePartial file path for the worker to dump raw coverage state to, or null to run without coverage
     * @param string|null $configPath explicit config file path to forward to the worker, or null to use the working directory lookup
     * @param StopConditions $stop the conditions the worker halts on, as its parent does
     */
    public function __construct(
        private readonly array $paths,
        private readonly string $phpspecBin,
        private readonly ?string $coveragePartial = null,
        private readonly ?string $configPath = null,
        private readonly StopConditions $stop = new StopConditions(),
    ) {
        $this->wire = new Wire();
    }

    /**
     * Builds the child process command line. Coverage-enabled workers run with
     * xdebug coverage mode and dump their raw coverage state to the partial path.
     *
     * @return list<string> the command and its arguments
     */
    public function buildCommand(): array
    {
        $command = [
            PHP_BINARY,
            '-d', 'xdebug.mode=' . ($this->coveragePartial !== null ? 'coverage' : 'off'),
            $this->phpspecBin,
            'run',
            ...$this->paths,
            '-f', 'wire',
            '--no-ansi',
            '--no-interaction',
        ];

        if ($this->coveragePartial !== null) {
            $command[] = '--coverage-partial=' . $this->coveragePartial;
        }

        if ($this->configPath !== null) {
            $command[] = '--config=' . $this->configPath;
        }

        array_push($command, ...$this->stop->options());

        return array_values($command);
    }

    /**
     * Spawns the child phpspec process with non-blocking stdout/stderr pipes.
     */
    public function start(): void
    {
        $command = $this->buildCommand();

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $cwd = getcwd();
        $process = proc_open($command, $descriptors, $this->pipes, $cwd !== false ? $cwd : null);

        if (!is_resource($process)) {
            throw new \RuntimeException('Failed to start worker process');
        }

        $this->process = $process;

        fclose($this->pipes[0]);
        unset($this->pipes[0]);

        stream_set_blocking($this->pipes[1], false);
        stream_set_blocking($this->pipes[2], false);
    }

    /**
     * Checks whether the child process is still running and captures the exit code when it finishes.
     */
    public function isRunning(): bool
    {
        if (!is_resource($this->process)) {
            return false;
        }

        $status = proc_get_status($this->process);
        if (!$status['running']) {
            $this->exitCode = $status['exitcode'];
            return false;
        }

        return true;
    }

    /**
     * Non-blocking read of available stdout/stderr data from the child process.
     */
    public function read(): void
    {
        if (!is_resource($this->process)) {
            return;
        }

        $readable = array_filter($this->pipes, 'is_resource');
        if (empty($readable)) {
            return;
        }

        $read = array_values($readable);
        $write = null;
        $except = null;

        if (@stream_select($read, $write, $except, 0) > 0) {
            foreach ($read as $pipe) {
                $content = stream_get_contents($pipe);
                if ($content !== false && $content !== '') {
                    if ($pipe === ($this->pipes[1] ?? null)) {
                        $this->stdout .= $content;
                    } else {
                        $this->stderr .= $content;
                    }
                }
            }
        }
    }

    /**
     * Sends SIGTERM to the child process and closes all pipes.
     */
    public function terminate(): void
    {
        $process = $this->process;
        if ($process !== null && is_resource($process)) {
            proc_terminate($process);
            $this->closePipes();
            proc_close($process);
            $this->process = null;
        }
    }

    /**
     * Returns the child process exit code, or null if still running.
     */
    public function getExitCode(): ?int
    {
        return $this->exitCode;
    }

    /**
     * Returns all captured stderr output from the child process.
     */
    public function getStderr(): string
    {
        return $this->stderr;
    }

    /**
     * The results whose lines have fully arrived since the last take, in the
     * order the worker reported them. A line still arriving waits for the
     * next read.
     *
     * @return list<SpecificationResult|FeatureResult>
     */
    public function takeResults(): array
    {
        $results = [];

        while (($newline = strpos($this->stdout, "\n")) !== false) {
            $line = rtrim(substr($this->stdout, 0, $newline), "\r");
            $this->stdout = substr($this->stdout, $newline + 1);
            $decoded = $this->wire->decode($line);

            if ($decoded === true) {
                $this->ended = true;
            } elseif ($decoded !== null) {
                $this->reported[] = $decoded->getPath();
                $results[] = $decoded;
            }
        }

        return $results;
    }

    /**
     * Everything the worker still had to report once it is done, and an error
     * on each file it was running but never reported when it died before
     * ending its report.
     *
     * @return list<SpecificationResult|FeatureResult>
     */
    public function getResults(): array
    {
        $this->drainPipes();
        $this->closePipes();
        if (is_resource($this->process)) {
            proc_close($this->process);
            $this->process = null;
        }

        $results = $this->takeResults();

        return $this->ended ? $results : [...$results, ...$this->unreported()];
    }

    /**
     * @return list<SpecificationResult>
     */
    private function unreported(): array
    {
        $reported = array_map(self::samePathKey(...), $this->reported);
        $results = [];

        foreach ($this->paths as $path) {
            if (in_array(self::samePathKey($path), $reported, true)) {
                continue;
            }

            $example = new ExampleResult(basename($path), [], true);
            $example->setError(ExampleError::fromReport($this->died($path), \RuntimeException::class));
            $results[] = new SpecificationResult(basename($path), [$example], $path);
        }

        return $results;
    }

    private function died(string $path): string
    {
        $message = sprintf(
            'The worker exited %s before reporting %s.',
            $this->exitCode === null ? 'without a code' : 'with code ' . $this->exitCode,
            $path,
        );
        $said = trim($this->stderr);

        return $said === '' ? $message : $message . "\n" . $said;
    }

    private static function samePathKey(string $path): string
    {
        return realpath($path) ?: $path;
    }

    /**
     * Switches pipes to blocking mode and reads all remaining data.
     */
    private function drainPipes(): void
    {
        if (isset($this->pipes[1]) && is_resource($this->pipes[1])) {
            stream_set_blocking($this->pipes[1], true);
            $content = stream_get_contents($this->pipes[1]);
            if ($content !== false && $content !== '') {
                $this->stdout .= $content;
            }
        }
        if (isset($this->pipes[2]) && is_resource($this->pipes[2])) {
            stream_set_blocking($this->pipes[2], true);
            $content = stream_get_contents($this->pipes[2]);
            if ($content !== false && $content !== '') {
                $this->stderr .= $content;
            }
        }
    }

    /**
     * Closes all open pipe resources and resets the pipes array.
     */
    private function closePipes(): void
    {
        foreach ($this->pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }
        $this->pipes = [];
    }
}
