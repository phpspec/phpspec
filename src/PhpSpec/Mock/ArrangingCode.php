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

namespace PhpSpec\Mock;

/**
 * @internal
 * Where the spec code lives. A file under one of these roots may call a
 * method of a double that nothing declared and get a default back, which is
 * how allow() and expect() reach the call they are about to declare. From
 * anywhere else such a call is unexpected. Until told any roots, every file
 * counts as spec code.
 */
final class ArrangingCode
{
    /** @var list<string> */
    private static array $roots = [];

    public static function under(string ...$roots): void
    {
        $resolved = [];

        foreach ($roots as $root) {
            $real = realpath($root);

            if ($real !== false && !in_array($real, $resolved, true)) {
                $resolved[] = $real;
            }
        }

        self::$roots = $resolved;
    }

    /**
     * @return list<string>
     */
    public static function roots(): array
    {
        return self::$roots;
    }

    public static function reset(): void
    {
        self::$roots = [];
    }

    public static function includes(string $file): bool
    {
        if (self::$roots === []) {
            return true;
        }

        $real = realpath($file);

        if ($real === false) {
            return false;
        }

        foreach (self::$roots as $root) {
            if ($real === $root || str_starts_with($real, $root . DIRECTORY_SEPARATOR)) {
                return true;
            }
        }

        return false;
    }
}
