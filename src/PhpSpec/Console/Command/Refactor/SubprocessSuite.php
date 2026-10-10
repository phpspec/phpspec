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

namespace PhpSpec\Console\Command\Refactor;

use PhpSpec\Parallel\ParallelRunner;

/**
 * @internal
 * Runs the project's whole spec suite in a process of its own, so the code a
 * refactoring just wrote is loaded fresh, and reads its agent stream. A run
 * that outlasts the timeout is stopped and read as red.
 */
final readonly class SubprocessSuite implements SpecSuite
{
    public function __construct(
        private string $directory,
        private int $timeoutSeconds = 300,
    ) {}

    public function check(): SuiteCheck
    {
        $command = [PHP_BINARY, '-d', 'xdebug.mode=off', ParallelRunner::findPhpspecBin(), 'run', '--format=agent', '--no-interaction', '--no-ansi'];
        $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $this->directory);

        if (!is_resource($process)) {
            return SuiteCheck::fromStream('', 'The spec run could not be started.');
        }

        fclose($pipes[0]);
        [$stdout, $stderr, $finished] = $this->collect($pipes, $process);

        return SuiteCheck::fromStream($stdout, $finished ? $stderr : sprintf('The spec run took longer than %d seconds and was stopped.', $this->timeoutSeconds));
    }

    /**
     * Reads both streams until the process ends or the time runs out.
     *
     * @param array<int, resource> $pipes
     * @param resource $process
     * @return array{0: string, 1: string, 2: bool} what it printed, what it wrote to the error stream, and whether it finished
     */
    private function collect(array $pipes, $process): array
    {
        $streams = [1 => '', 2 => ''];
        $deadline = microtime(true) + $this->timeoutSeconds;
        $finished = false;

        while (!$finished && microtime(true) < $deadline) {
            $read = [$pipes[1], $pipes[2]];
            $write = null;
            $except = null;

            if (stream_select($read, $write, $except, 0, 200_000) === false) {
                break;
            }

            foreach ($read as $pipe) {
                $streams[$pipe === $pipes[1] ? 1 : 2] .= (string) fread($pipe, 8192);
            }

            $finished = !proc_get_status($process)['running'];
        }

        if ($finished) {
            $streams[1] .= (string) stream_get_contents($pipes[1]);
            $streams[2] .= (string) stream_get_contents($pipes[2]);
        } else {
            proc_terminate($process);
        }

        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        return [$streams[1], $streams[2], $finished];
    }
}
