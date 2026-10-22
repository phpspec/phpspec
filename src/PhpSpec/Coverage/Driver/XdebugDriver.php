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
 * Requires Xdebug with coverage mode enabled.
 */
final class XdebugDriver implements CoverageDriver
{
    /**
     * Starts or resumes Xdebug coverage collection with unused and dead code
     * analysis.
     */
    public function start(): void
    {
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
}
