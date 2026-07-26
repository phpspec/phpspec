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
     * Normalises a path as the user typed it: separators to forward slashes,
     * a leading dot-slash stripped. No cwd involved, so it is safe for tokens
     * lifted out of an instruction.
     */
    public static function normalize(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        if (str_starts_with($path, './')) {
            $path = substr($path, 2);
        }

        return $path;
    }

    /**
     * The path-like tokens (ending .feature or .php) named in a piece of text,
     * each once, in order of appearance. Backslash separators are captured, so
     * a Windows-typed path stays one token; normalize() makes it canonical.
     *
     * @return list<string>
     */
    public static function tokensIn(string $text): array
    {
        preg_match_all('~[A-Za-z0-9_./\\\\-]+\.(?:feature|php)~', $text, $matches);

        return array_values(array_unique($matches[0]));
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
