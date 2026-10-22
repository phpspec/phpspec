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

namespace PhpSpec\Coverage\Driver;

use PhpSpec\Coverage\CoverageDriver;

/**
 * @internal
 * Xdebug-backed coverage driver. Each cycle up to stop() is a fresh set of
 * execution counts, with unused and dead code analysis so that
 * executable-but-unexecuted lines are reported; from Xdebug 3.6 that analysis
 * may also cover files compiled before the cycle began.
 * It records only the directories it traces, as PCOV does with its
 * pcov.directory, for the files compiled once it starts.
 * Requires Xdebug with coverage mode enabled.
 */
final class XdebugDriver implements CoverageDriver
{
    private bool $filtered = false;

    /**
     * @param list<string> $traced the directories to record, or none to record every file
     */
    public function __construct(private readonly array $traced = []) {}
    /**
     * Starts or resumes Xdebug coverage collection with unused and dead code
     * analysis.
     */
    public function start(): void
    {
        $this->filter();
        xdebug_start_code_coverage(XDEBUG_CC_UNUSED | XDEBUG_CC_DEAD_CODE);
    }

    public function pause(): void
    {
        xdebug_stop_code_coverage(false);
    }

    /**
     * Stops the current Xdebug collection cycle and returns the collected data.
     *
     * @return array<string, array<int, int>> file paths mapped to line-level hit counts
     */
    public function stop(): array
    {
        // Xdebug discards what it recorded only when it stops while recording.
        $this->start();
        $data = xdebug_get_code_coverage();
        xdebug_stop_code_coverage();

        return $data;
    }

    /**
     * Sets the process's filter to the traced directories the first time this
     * driver starts, so a filter another driver set never carries over.
     */
    private function filter(): void
    {
        if ($this->filtered) {
            return;
        }

        $this->filtered = true;
        $prefixes = [];

        foreach ($this->traced as $directory) {
            $real = realpath($directory);

            if ($real !== false) {
                $prefixes[] = rtrim($real, '/\\') . DIRECTORY_SEPARATOR;
            }
        }

        xdebug_set_filter(XDEBUG_FILTER_CODE_COVERAGE, $prefixes === [] ? XDEBUG_FILTER_NONE : XDEBUG_PATH_INCLUDE, $prefixes);
    }
}
