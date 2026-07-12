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

namespace PhpSpec\Console\Command\Refactor;

/**
 * @internal
 * Line-level diff utility using the Longest Common Subsequence algorithm.
 */
final class Diff
{
    /**
     * Computes a line-level diff between old and new content using LCS.
     *
     * @param string[] $old
     * @param string[] $new
     * @return array<array{type: string, line: int, text: string}>
     */
    public static function compute(array $old, array $new): array
    {
        $oldLen = count($old);
        $newLen = count($new);

        // Build LCS table
        /** @var array<int, array<int, int>> $lcs */
        $lcs = [];
        for ($i = 0; $i <= $oldLen; $i++) {
            $lcs[$i] = [];
            for ($j = 0; $j <= $newLen; $j++) {
                $lcs[$i][$j] = 0;
            }
        }
        for ($i = 1; $i <= $oldLen; $i++) {
            for ($j = 1; $j <= $newLen; $j++) {
                if ($old[$i - 1] === $new[$j - 1]) {
                    $lcs[$i][$j] = $lcs[$i - 1][$j - 1] + 1;
                } else {
                    $lcs[$i][$j] = max($lcs[$i - 1][$j], $lcs[$i][$j - 1]);
                }
            }
        }

        // Backtrack to produce diff entries
        $result = [];
        $i = $oldLen;
        $j = $newLen;

        while ($i > 0 || $j > 0) {
            if ($i > 0 && $j > 0 && $old[$i - 1] === $new[$j - 1]) {
                array_unshift($result, ['type' => ' ', 'line' => $j, 'text' => $new[$j - 1]]);
                $i--;
                $j--;
            } elseif ($j > 0 && ($i === 0 || $lcs[$i][$j - 1] >= $lcs[$i - 1][$j])) {
                array_unshift($result, ['type' => '+', 'line' => $j, 'text' => $new[$j - 1]]);
                $j--;
            } else {
                array_unshift($result, ['type' => '-', 'line' => $i, 'text' => $old[$i - 1]]);
                $i--;
            }
        }

        return self::renumber(self::slideChangesDown($result));
    }

    /**
     * Slides runs of inserted or deleted lines downward so an appended block
     * is anchored on the genuinely new lines rather than on identical lines
     * that happen to precede it. Mirrors git's diff hunk-shifting: when the
     * context line immediately after a change run equals the run's first line,
     * the run can move down one line without changing which file it rebuilds.
     * Without this, LCS parks an ambiguous insertion at its earliest position
     * — e.g. crediting a preceding method's braces with the added lines.
     *
     * @param array<array{type: string, line: int, text: string}> $diff
     * @return array<array{type: string, line: int, text: string}>
     */
    private static function slideChangesDown(array $diff): array
    {
        $count = count($diff);

        do {
            $moved = false;
            $i = 0;
            while ($i < $count) {
                $type = $diff[$i]['type'];
                if ($type !== '+' && $type !== '-') {
                    $i++;
                    continue;
                }

                // Extent of the maximal run of this single change type.
                $start = $i;
                $end = $i;
                while ($end + 1 < $count && $diff[$end + 1]['type'] === $type) {
                    $end++;
                }

                // While the following context line equals the run's first
                // line, rotate the run down one: the first change becomes
                // context and that context line becomes the new trailing change.
                while ($end + 1 < $count
                    && $diff[$end + 1]['type'] === ' '
                    && $diff[$start]['text'] === $diff[$end + 1]['text']
                ) {
                    $diff[$start] = ['type' => ' ', 'line' => $diff[$start]['line'], 'text' => $diff[$start]['text']];
                    $diff[$end + 1] = ['type' => $type, 'line' => $diff[$end + 1]['line'], 'text' => $diff[$end + 1]['text']];
                    $start++;
                    $end++;
                    $moved = true;
                }

                $i = $end + 1;
            }
        } while ($moved);

        return $diff;
    }

    /**
     * Recomputes display line numbers after entries have been reordered:
     * additions and context lines use the new-file line number, deletions
     * the old-file line number.
     *
     * @param array<array{type: string, line: int, text: string}> $diff
     * @return array<array{type: string, line: int, text: string}>
     */
    private static function renumber(array $diff): array
    {
        $oldLine = 0;
        $newLine = 0;
        foreach ($diff as $k => $entry) {
            if ($entry['type'] === '-') {
                $oldLine++;
                $diff[$k]['line'] = $oldLine;
            } elseif ($entry['type'] === '+') {
                $newLine++;
                $diff[$k]['line'] = $newLine;
            } else {
                $oldLine++;
                $newLine++;
                $diff[$k]['line'] = $newLine;
            }
        }

        return $diff;
    }

    /**
     * Formats diff entries as a colored string for console output.
     *
     * @param array<array{type: string, line: int, text: string}> $diff
     */
    public static function format(array $diff): string
    {
        $lines = [];
        foreach ($diff as $entry) {
            $lineNum = str_pad((string) $entry['line'], 4, ' ', STR_PAD_LEFT);
            if ($entry['type'] === '+') {
                $lines[] = "  <fg=green>$lineNum + {$entry['text']}</>";
            } elseif ($entry['type'] === '-') {
                $lines[] = "  <fg=red>$lineNum - {$entry['text']}</>";
            } else {
                $lines[] = "  <fg=gray>$lineNum   {$entry['text']}</>";
            }
        }
        return implode("\n", $lines);
    }
}
