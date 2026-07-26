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

namespace PhpSpec\Ai\Agent;

/**
 * @internal
 * The one project-path normaliser for the AI surfaces. Separators are
 * normalised on BOTH sides before any starts-with comparison, so a Windows
 * path (mixed "\" and "/") never silently fails to strip, and every proposal,
 * grounding entry, and capture shows the same project-relative form.
 */
final class ProjectPath
{
    /**
     * The project-relative form of a path, with forward slashes: an absolute
     * path under the cwd is stripped to relative; anything else keeps its own
     * shape minus any leading slashes.
     */
    public static function relative(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $cwd = str_replace('\\', '/', getcwd() ?: '.') . '/';
        if (str_starts_with($path, $cwd)) {
            $path = substr($path, strlen($cwd));
        }

        return ltrim($path, '/');
    }

    /**
     * The nullable form, for optional scan results (recency and friends).
     */
    public static function relativeOrNull(?string $path): ?string
    {
        return $path === null ? null : self::relative($path);
    }

    /**
     * The absolute path for a project-relative one.
     */
    public static function absolute(string $relPath): string
    {
        return (getcwd() ?: '.') . '/' . $relPath;
    }
}
