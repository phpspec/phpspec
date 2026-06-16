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

namespace PhpSpec\Ai;

/** @internal */

final class SpecRunner
{
    private const TIMEOUT_SECONDS = 60;

    /**
     * Runs specs via subprocess with closed STDIN and a timeout.
     *
     * @param string $specPath path to the spec file to execute
     *
     * @return array{0: int, 1: string} [exitCode, output]
     */
    public static function run(string $specPath): array
    {
        $cmd = self::buildCommand($specPath);

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $cwd = getcwd();
        $process = proc_open($cmd, $descriptors, $pipes, $cwd !== false ? $cwd : null);

        if (!is_resource($process)) {
            return [1, 'Failed to start phpspec process'];
        }

        fclose($pipes[0]);

        return self::waitForProcess($process, $pipes);
    }

    /**
     * @param resource $process
     * @param resource[] $pipes
     * @return array{0: int, 1: string}
     */
    private static function waitForProcess($process, array $pipes): array
    {
        $output = '';
        $deadline = microtime(true) + self::TIMEOUT_SECONDS;
        $status = [];

        while (true) {
            $read = [$pipes[1], $pipes[2]];
            $write = null;
            $except = null;
            $remaining = max(0, $deadline - microtime(true));

            $ready = stream_select($read, $write, $except, (int) $remaining, (int) (($remaining - (int) $remaining) * 1_000_000));

            if ($ready === false) {
                break;
            }

            foreach ($read as $pipe) {
                $output .= fread($pipe, 8192);
            }

            $status = proc_get_status($process);
            if (!$status['running']) {
                break;
            }

            if (microtime(true) >= $deadline) {
                proc_terminate($process);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($process);
                return [1, trim($output) . "\n\nProcess timed out after " . self::TIMEOUT_SECONDS . ' seconds'];
            }
        }

        $output .= stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        $exitCode = proc_get_status($process)['exitcode'];
        if ($exitCode === -1) {
            $exitCode = $status['exitcode'] ?? 1;
        }

        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        return [$exitCode, trim($output)];
    }

    /**
     * @return list<string>
     */
    private static function buildCommand(string $specPath): array
    {
        $phpspecBin = realpath(getcwd() . '/bin/phpspec') ?: getcwd() . '/bin/phpspec';

        return [
            PHP_BINARY,
            '-d', 'xdebug.mode=off',
            $phpspecBin,
            'run',
            $specPath,
            '--no-ansi',
        ];
    }
}
