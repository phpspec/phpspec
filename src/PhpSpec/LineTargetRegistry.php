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
 * the current targets when pruning examples; with no targets set (the normal
 * case) those consultations are no-ops.
 */
final class LineTargetRegistry
{
    /** @var array<string, list<int>> targeted line numbers keyed by normalised spec path */
    private static array $targets = [];

    /** @var list<int> */
    private static array $current = [];

    /**
     * Registers line targets for a spec file.
     *
     * @param string $path the spec file path as given on the command line
     * @param int ...$lines the targeted line numbers
     */
    public static function add(string $path, int ...$lines): void
    {
        $key = self::normalise($path);
        self::$targets[$key] = array_merge(self::$targets[$key] ?? [], array_values($lines));
    }

    /**
     * Records the spec file that is about to run, exposing its line targets.
     *
     * @param string $path the spec file path
     */
    public static function beginSpec(string $path): void
    {
        self::$current = self::$targets[self::normalise($path)] ?? [];
    }

    /**
     * The line targets of the currently running spec, none when it runs whole.
     *
     * @return list<int>
     */
    public static function currentTargets(): array
    {
        return self::$current;
    }

    /**
     * Removes all targets and the current targets.
     */
    public static function reset(): void
    {
        self::$targets = [];
        self::$current = [];
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
