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
use PhpSpec\Result\MatchResult;
use PhpSpec\Result\ScenarioResult;
use PhpSpec\Result\SpecificationResult;
use PhpSpec\Result\StepResult;
use PhpSpec\Specification\ExampleError;
use PhpSpec\StoryBDD\StepError;

/**
 * Wraps a single child process that runs phpspec on a set of spec files
 * and produces JUnit XML output for result parsing.
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

    /**
     * @param string[] $paths spec file paths to run in this worker
     * @param string $phpspecBin absolute path to the phpspec binary
     */
    public function __construct(
        private readonly array $paths,
        private readonly string $phpspecBin,
    ) {}

    /**
     * Spawns the child phpspec process with non-blocking stdout/stderr pipes.
     */
    public function start(): void
    {
        $command = array_values([
            PHP_BINARY,
            '-d', 'xdebug.mode=off',
            $this->phpspecBin,
            'run',
            ...$this->paths,
            '-f', 'junit',
            '--no-ansi',
            '--no-interaction',
        ]);

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
     * Parses JUnit XML output from the child process into result objects.
     *
     * @return array<SpecificationResult|FeatureResult>
     */
    public function getResults(): array
    {
        $this->drainPipes();
        $this->closePipes();
        if (is_resource($this->process)) {
            proc_close($this->process);
            $this->process = null;
        }

        if ($this->stdout === '') {
            return [];
        }

        $xmlStart = strpos($this->stdout, '<?xml');
        if ($xmlStart === false) {
            return [];
        }

        $prev = libxml_use_internal_errors(true);
        try {
            $xml = new \SimpleXMLElement(substr($this->stdout, $xmlStart));
        } catch (\Exception) {
            return [];
        } finally {
            libxml_use_internal_errors($prev);
        }

        return self::parseJunitXml($xml);
    }

    /**
     * @return array<SpecificationResult|FeatureResult>
     */
    private static function parseJunitXml(\SimpleXMLElement $xml): array
    {
        $results = [];

        foreach ($xml->testsuite as $testsuite) {
            $type = (string) ($testsuite['type'] ?? '');

            if ($type === 'feature') {
                $results[] = self::parseFeatureSuite($testsuite);
            } else {
                $results[] = self::parseSpecSuite($testsuite);
            }
        }

        return $results;
    }

    /**
     * Parses a spec-level testsuite element into a SpecificationResult.
     */
    private static function parseSpecSuite(\SimpleXMLElement $testsuite): SpecificationResult
    {
        $suiteName = (string) $testsuite['name'];
        $examples = [];

        foreach ($testsuite->testcase as $testcase) {
            $title = (string) $testcase['name'];

            if (isset($testcase->skipped)) {
                $examples[] = new ExampleResult($title, [], false, false, true);
            } elseif (isset($testcase->error)) {
                $message = (string) ($testcase->error['message'] ?? 'Error');
                $result = new ExampleResult($title, [], true);
                $result->setError(new ExampleError($message, new \RuntimeException($message)));
                $examples[] = $result;
            } elseif (isset($testcase->failure)) {
                $message = (string) ($testcase->failure['message'] ?? 'Failed');
                $examples[] = new ExampleResult($title, [
                    MatchResult::failed(null, null, $message, '', 0),
                ]);
            } else {
                $examples[] = new ExampleResult($title, []);
            }
        }

        return new SpecificationResult($suiteName, $examples);
    }

    /**
     * Parses a feature-level testsuite element into a FeatureResult with scenarios and steps.
     */
    private static function parseFeatureSuite(\SimpleXMLElement $featureXml): FeatureResult
    {
        $scenarios = [];

        foreach ($featureXml->testsuite as $scenarioXml) {
            $steps = [];

            foreach ($scenarioXml->testcase as $testcase) {
                $title = (string) $testcase['name'];

                if (isset($testcase->skipped)) {
                    $steps[] = new StepResult($title, 'pending');
                } elseif (isset($testcase->failure)) {
                    $message = (string) ($testcase->failure['message'] ?? 'Failed');
                    $step = new StepResult($title, 'failure');
                    $step->setError(new StepError($message, new \RuntimeException($message)));
                    $steps[] = $step;
                } else {
                    $steps[] = new StepResult($title, 'passed');
                }
            }

            $scenarios[] = new ScenarioResult((string) $scenarioXml['name'], $steps);
        }

        return new FeatureResult((string) $featureXml['name'], $scenarios);
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
