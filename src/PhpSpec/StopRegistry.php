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

namespace PhpSpec;

/**
 * @internal
 * Holds the stop conditions of the current run and whether one has been met.
 * Every level of the run consults it after a result, so the run halts at the
 * example or scenario that meets a condition rather than at the end of its
 * file; with no conditions active (the normal case) those consultations are
 * no-ops.
 */
final class StopRegistry
{
    private static ?StopConditions $conditions = null;

    private static bool $halted = false;

    public static function activate(StopConditions $conditions): void
    {
        self::$conditions = $conditions;
        self::$halted = false;
    }

    /**
     * Whether the run is to halt, judging this result if nothing has met the
     * conditions yet.
     */
    public static function reached(Results $result): bool
    {
        if (!self::$halted && self::$conditions !== null && self::$conditions->metBy($result)) {
            self::$halted = true;
        }

        return self::$halted;
    }

    public static function halted(): bool
    {
        return self::$halted;
    }

    public static function reset(): void
    {
        self::$conditions = null;
        self::$halted = false;
    }
}
