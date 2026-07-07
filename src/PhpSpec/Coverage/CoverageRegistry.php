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
 * Holds the active per-example coverage collector for the current run.
 * The runner consults this registry at example boundaries; when no collector
 * is active (the normal case) those consultations are no-ops.
 */
final class CoverageRegistry
{
    private static ?PerExampleCollector $collector = null;

    /**
     * Activates per-example coverage collection for the current run.
     *
     * @param PerExampleCollector $collector the collector to notify at example boundaries
     */
    public static function activate(PerExampleCollector $collector): void
    {
        self::$collector = $collector;
    }

    /**
     * Returns the active collector, or null when per-example coverage is off.
     */
    public static function collector(): ?PerExampleCollector
    {
        return self::$collector;
    }

    /**
     * Deactivates per-example coverage collection.
     */
    public static function reset(): void
    {
        self::$collector = null;
    }
}
