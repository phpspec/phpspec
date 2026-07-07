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

namespace PhpSpec\Coverage;

/**
 * @internal
 * Abstracts the underlying line coverage engine so collectors can be
 * exercised in specs without a live Xdebug extension.
 */
interface CoverageDriver
{
    /**
     * Starts a fresh line coverage collection cycle.
     */
    public function start(): void;

    /**
     * Stops the current collection cycle and returns the collected data.
     *
     * @return array<string, array<int, int>> file paths mapped to line-level hit counts
     */
    public function stop(): array;
}
