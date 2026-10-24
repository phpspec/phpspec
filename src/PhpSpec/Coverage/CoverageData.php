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

namespace PhpSpec\Coverage;

/**
 * @internal
 * Reads collected coverage data: the lines it counts, and the files under the
 * source path.
 */
final class CoverageData
{
    /**
     * Counts covered and executable lines from a single file's coverage data.
     * Lines with hit value -2 are non-executable and skipped.
     *
     * @param array<int, int> $lines line-level hit counts for one file
     * @return array{covered: int, executable: int} the line counts
     */
    public static function countLines(array $lines): array
    {
        $covered = 0;
        $executable = 0;

        foreach ($lines as $hit) {
            if ($hit === -2) {
                continue;
            }
            $executable++;
            if ($hit >= 1) {
                $covered++;
            }
        }

        return ['covered' => $covered, 'executable' => $executable];
    }

    /**
     * Filters raw coverage data to only include files under the given source path.
     * Returns relative paths sorted alphabetically, using forward slashes on
     * every platform (an engine and realpath() report backslash paths on Windows).
     *
     * @param array<string, array<int, int>> $data raw coverage data keyed by absolute file path
     * @param string $srcPath the source directory path to filter by
     * @return array<string, array<int, int>> relative file paths mapped to line-level hit counts
     */
    public static function filterData(array $data, string $srcPath): array
    {
        $realSrcPath = realpath($srcPath);

        if ($realSrcPath === false) {
            return [];
        }
        $realSrcPath = rtrim(str_replace('\\', '/', $realSrcPath), '/') . '/';

        $filtered = [];

        foreach ($data as $file => $lines) {
            $file = str_replace('\\', '/', $file);

            if (str_starts_with($file, $realSrcPath)
                && !str_contains($file, "eval()'d code")
            ) {
                $relative = substr($file, strlen($realSrcPath));
                $filtered[$relative] = $lines;
            }
        }

        ksort($filtered);

        return $filtered;
    }
}
