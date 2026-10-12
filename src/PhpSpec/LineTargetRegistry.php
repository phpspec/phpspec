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

    /** @var array<string, list<string>> targeted example ids keyed by normalised spec path */
    private static array $ids = [];

    /** @var list<string> */
    private static array $currentIds = [];

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
        self::$currentIds = self::$ids[self::normalise($path)] ?? [];
    }

    /**
     * Registers example ids for a spec file, from "file.spec.php@ID" run paths.
     *
     * @param string $path the spec file path as given on the command line
     * @param string ...$ids the targeted example ids
     */
    public static function addIds(string $path, string ...$ids): void
    {
        $key = self::normalise($path);
        self::$ids[$key] = array_merge(self::$ids[$key] ?? [], array_values($ids));
    }

    /**
     * The example ids the currently running spec is targeted by.
     *
     * @return list<string>
     */
    public static function currentIds(): array
    {
        return self::$currentIds;
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
        self::$ids = [];
        self::$currentIds = [];
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
