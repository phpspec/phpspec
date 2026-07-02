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
 * Wraps Xdebug code coverage collection, providing start/stop lifecycle and source path filtering.
 * Requires Xdebug with coverage mode enabled.
 */
final class CoverageCollector
{
    /** @var array<string, array<int, int>> raw coverage data keyed by file path */
    private array $data = [];

    /** @var bool whether a whole-suite collection is currently in progress */
    private static bool $collecting = false;

    /**
     * Checks whether Xdebug is loaded with coverage mode enabled.
     *
     * @return bool true if code coverage collection is available
     */
    public static function isAvailable(): bool
    {
        return extension_loaded('xdebug')
            && in_array('coverage', xdebug_info('mode'));
    }

    /**
     * Checks whether a whole-suite collection is currently in progress.
     * Xdebug coverage state is process-global, so cycling it (as the
     * per-example driver does) would clobber a live whole-suite collection.
     *
     * @return bool true if a collection has been started and not yet stopped
     */
    public static function isCollecting(): bool
    {
        return self::$collecting;
    }

    /**
     * Starts Xdebug code coverage collection with unused and dead code analysis.
     */
    public function start(): void
    {
        self::$collecting = true;
        xdebug_start_code_coverage(XDEBUG_CC_UNUSED | XDEBUG_CC_DEAD_CODE);
    }

    /**
     * Stops Xdebug coverage collection and stores the collected data.
     */
    public function stop(): void
    {
        $this->data = xdebug_get_code_coverage();
        xdebug_stop_code_coverage();
        self::$collecting = false;
    }

    /**
     * Returns the raw coverage data collected by Xdebug.
     *
     * @return array<string, array<int, int>> file paths mapped to line-level hit counts
     */
    public function getData(): array
    {
        return $this->data;
    }

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
     * Filters coverage data to only include files under the given source path.
     * Returns relative paths sorted alphabetically.
     *
     * @param string $srcPath the source directory path to filter by
     * @return array<string, array<int, int>> relative file paths mapped to line-level hit counts
     */
    public function filter(string $srcPath): array
    {
        return self::filterData($this->data, $srcPath);
    }

    /**
     * Filters raw coverage data to only include files under the given source path.
     * Returns relative paths sorted alphabetically.
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
        $realSrcPath = rtrim($realSrcPath, '/') . '/';

        $filtered = [];

        foreach ($data as $file => $lines) {
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
