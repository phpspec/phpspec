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
 * Holds the active title filter for the current run. The runner consults
 * this registry when pruning examples; when no filter is active (the normal
 * case) those consultations are no-ops.
 */
final class FilterRegistry
{
    private static ?TitleFilter $filter = null;

    /**
     * Activates a title filter for the current run.
     *
     * @param TitleFilter $filter the filter to consult when pruning examples
     */
    public static function activate(TitleFilter $filter): void
    {
        self::$filter = $filter;
    }

    /**
     * Returns the active filter, or null when no filter is set.
     */
    public static function current(): ?TitleFilter
    {
        return self::$filter;
    }

    /**
     * Deactivates the title filter.
     */
    public static function reset(): void
    {
        self::$filter = null;
    }
}
