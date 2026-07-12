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

namespace PhpSpec\Console\Command\Pair;

use PhpSpec\Console\Command\Run\GenerationReport;
use PhpSpec\Console\Command\Run\RunOutcome;
use PhpSpec\Parallel\ParallelRunner;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 * Runs a pair-mode `run` in a fresh subprocess so that code generated earlier
 * in the session (a new class, a new method) is actually loaded — PHP cannot
 * redefine an already-loaded class in the long-lived REPL process, so an
 * in-process re-run would keep seeing the stale definition.
 *
 * The child is told where to report its generation candidates through an
 * environment variable (see GenerationReport), pointing at a unique file under
 * .phpspec/pair/. Nothing is discovered ambiently, so a normal `phpspec run`
 * is never affected.
 */
final class SubprocessRunner implements SpecRunner
{
    public function run(string $argument, OutputInterface $output): ?RunOutcome
    {
        $reportPath = self::reportPath();
        $this->ensureDir(dirname($reportPath));

        try {
            $command = [
                PHP_BINARY,
                '-d', 'xdebug.mode=off',
                ParallelRunner::findPhpspecBin(),
                'run',
            ];
            if ($argument !== '') {
                $command[] = $argument;
            }
            $command[] = '-f';
            $command[] = 'dot';
            $command[] = '--no-interaction';
            if ($output->isDecorated()) {
                $command[] = '--ansi';
            }

            $env = array_merge(getenv(), [GenerationReport::ENV_VAR => $reportPath]);

            $this->stream($command, $env, $output);

            return GenerationReport::read($reportPath);
        } finally {
            if (is_file($reportPath)) {
                @unlink($reportPath);
            }
        }
    }

    /**
     * A unique report file path under the project's .phpspec/pair/ directory.
     *
     * @return string the absolute report file path
     */
    private static function reportPath(): string
    {
        return (getcwd() ?: '.') . '/.phpspec/pair/report-' . uniqid() . '.json';
    }

    /**
     * Ensures the given directory exists.
     *
     * @param string $dir the directory to create
     * @return void
     */
    private function ensureDir(string $dir): void
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    /**
     * Runs the subprocess, forwarding its output live to $output.
     *
     * @param list<string> $command the command and its arguments
     * @param array<string, string> $env the child environment
     * @param OutputInterface $output the output to stream into
     */
    private function stream(array $command, array $env, OutputInterface $output): void
    {
        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = proc_open($command, $descriptors, $pipes, getcwd() ?: null, $env);

        if (!is_resource($process)) {
            $output->writeln('  <fg=red>Could not start the spec runner.</>');
            return;
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $stderr = '';
        $streams = [1 => $pipes[1], 2 => $pipes[2]];

        while ($streams !== []) {
            $read = array_values($streams);
            $write = $except = [];
            if (@stream_select($read, $write, $except, 0, 200000) === false) {
                break;
            }

            foreach ($read as $stream) {
                $chunk = fread($stream, 8192);
                if ($chunk !== false && $chunk !== '') {
                    if ($stream === $pipes[1]) {
                        $output->write($chunk, false, OutputInterface::OUTPUT_RAW);
                    } else {
                        $stderr .= $chunk;
                    }
                }
                if (feof($stream)) {
                    fclose($stream);
                    unset($streams[$stream === $pipes[1] ? 1 : 2]);
                }
            }
        }

        proc_close($process);

        if (trim($stderr) !== '') {
            $output->write($stderr, false, OutputInterface::OUTPUT_RAW);
        }
    }
}
