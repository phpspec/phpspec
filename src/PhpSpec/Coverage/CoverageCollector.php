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
 * One collection around the whole run: start, stop, and the data filtered to
 * the source path.
 */
final class CoverageCollector
{
    /** @var array<string, array<int, int>> raw coverage data keyed by file path */
    private array $data = [];

    /** @var bool whether a whole-suite collection is currently in progress */
    private static bool $collecting = false;

    public function __construct(private readonly CoverageDriver $driver) {}

    /**
     * Checks whether a whole-suite collection is currently in progress.
     * An engine's coverage state is process-global, so cycling it (as the
     * per-example collector does) would clobber a live whole-suite collection.
     *
     * @return bool true if a collection has been started and not yet stopped
     */
    public static function isCollecting(): bool
    {
        return self::$collecting;
    }

    public function start(): void
    {
        self::$collecting = true;
        $this->driver->start();
    }

    public function stop(): void
    {
        $this->data = $this->driver->stop();
        self::$collecting = false;
    }

    /**
     * Returns the raw coverage data the engine collected.
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
