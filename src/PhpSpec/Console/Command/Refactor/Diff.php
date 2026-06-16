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

        return $result;
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
