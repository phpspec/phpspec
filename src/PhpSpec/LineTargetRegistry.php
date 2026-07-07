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
 * Holds the line targets from "file.spec.php:LINE" run paths for the current
 * run. The runner announces each spec file as it starts, and contexts consult
 * the current target when pruning examples; with no targets set (the normal
 * case) those consultations are no-ops.
 */
final class LineTargetRegistry
{
    /** @var array<string, int> targeted line numbers keyed by normalised spec path */
    private static array $targets = [];

    private static ?int $current = null;

    /**
     * Registers a line target for a spec file.
     *
     * @param string $path the spec file path as given on the command line
     * @param int $line the targeted line number
     */
    public static function add(string $path, int $line): void
    {
        self::$targets[self::normalise($path)] = $line;
    }

    /**
     * Records the spec file that is about to run, exposing its line target.
     *
     * @param string $path the spec file path
     */
    public static function beginSpec(string $path): void
    {
        self::$current = self::$targets[self::normalise($path)] ?? null;
    }

    /**
     * Returns the line target of the currently running spec, or null.
     */
    public static function currentTarget(): ?int
    {
        return self::$current;
    }

    /**
     * Removes all targets and the current target.
     */
    public static function reset(): void
    {
        self::$targets = [];
        self::$current = null;
    }

    /**
     * Normalises a path for comparison, stripping a leading "./".
     *
     * @param string $path the path to normalise
     * @return string the normalised path
     */
    private static function normalise(string $path): string
    {
        return str_starts_with($path, './') ? substr($path, 2) : $path;
    }
}
