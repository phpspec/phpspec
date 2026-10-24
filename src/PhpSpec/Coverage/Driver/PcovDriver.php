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

use const pcov\all;

use function pcov\clear;
use function pcov\collect;
use function pcov\start;
use function pcov\stop;

use PhpSpec\Coverage\CoverageDriver;

/**
 * @internal
 * PCOV-backed coverage driver. Each stop() reports the lines executed since
 * the last one as 1 and the executable lines not reached as -1,
 * for every file PCOV has seen compiled; a line that cannot execute is not
 * listed. Requires PCOV enabled.
 */
final class PcovDriver implements CoverageDriver
{
    public function start(): void
    {
        start();
    }

    public function pause(): void
    {
        stop();
    }

    /**
     * @return array<string, array<int, int>> file paths mapped to line-level hit values
     */
    public function stop(): array
    {
        stop();
        $data = collect(all);
        clear();

        return $data;
    }
}
