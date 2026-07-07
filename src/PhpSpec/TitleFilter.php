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
 * Matches example and scenario titles (and file paths) against the --filter
 * text using case-insensitive substring comparison. A leading "it" on the
 * filter is ignored, so --filter="it should be good" matches the example
 * title "should be good". While a spec whose path matches is running, every
 * title in it matches, preserving the file-path filtering behaviour.
 */
final class TitleFilter
{
    private readonly string $needle;

    private bool $currentSpecMatches = false;

    /**
     * @param string $filter the raw --filter option value
     */
    public function __construct(private readonly string $filter)
    {
        $needle = trim($filter);

        if (preg_match('/^it[\s_]+(.+)$/i', $needle, $matches) === 1) {
            $needle = $matches[1];
        }

        $this->needle = $needle;
    }

    /**
     * Records the spec file that is about to run, so titles inside a
     * path-matched file always match.
     *
     * @param string $path the spec file path
     */
    public function beginSpec(string $path): void
    {
        $this->currentSpecMatches = $this->matchesPath($path);
    }

    /**
     * Checks whether an example or scenario title matches the filter,
     * or whether the current spec file already matched by path.
     *
     * @param string $title the example or scenario title
     * @return bool true when the title (or the current spec path) matches
     */
    public function matches(string $title): bool
    {
        if ($this->currentSpecMatches) {
            return true;
        }

        return stripos($title, $this->needle) !== false;
    }

    /**
     * Checks whether a file path matches the filter text.
     *
     * @param string $path the spec or feature file path
     * @return bool true when the path contains the filter text
     */
    public function matchesPath(string $path): bool
    {
        return stripos($path, $this->filter) !== false;
    }
}
