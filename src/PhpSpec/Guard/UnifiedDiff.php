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

namespace PhpSpec\Guard;

/**
 * @internal
 * Reads a unified diff for the only thing guard asks of it: which lines are
 * new, and in which file, numbered as the file stands now.
 *
 * Removed lines are ignored, so a hunk that only deletes contributes nothing.
 */
final class UnifiedDiff
{
    /**
     * @return array<string, list<int>> added line numbers by path, as the diff names them after the change
     */
    public static function added(string $diff): array
    {
        $added = [];
        $file = null;
        $line = 0;

        foreach (explode("\n", $diff) as $text) {
            if (str_starts_with($text, '+++ ')) {
                $named = substr($text, 4);
                // "/dev/null" is a file the diff deleted; there is nothing new in it.
                $file = $named === '/dev/null' ? null : preg_replace('#^[ab]/#', '', $named);

                continue;
            }

            if (str_starts_with($text, '@@')) {
                // @@ -old,count +new,count @@ : the number this hunk starts at
                // in the file as it now stands.
                $line = preg_match('/^@@ -\d+(?:,\d+)? \+(\d+)/', $text, $matches) === 1 ? (int) $matches[1] : 0;

                continue;
            }

            if ($file === null || $line === 0) {
                continue;
            }

            if (str_starts_with($text, '+')) {
                $added[$file][] = $line;
                $line++;
            } elseif (str_starts_with($text, ' ')) {
                $line++;
            }
            // A "-" line is not in the file as it now stands, so it does not
            // advance the count.
        }

        return $added;
    }
}
