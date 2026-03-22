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

    /** @var int flush call counter for periodic draining */
    private int $flushCount = 0;

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
     * Starts Xdebug code coverage collection.
     * Uses XDEBUG_CC_UNUSED only — XDEBUG_CC_DEAD_CODE causes segfaults
     * on large suites due to unbounded internal state accumulation.
     */
    public function start(): void
    {
        xdebug_start_code_coverage(XDEBUG_CC_UNUSED);
    }

    /**
     * Periodically drains Xdebug's internal buffers to prevent memory
     * corruption on large suites. Call after each example.
     * Every 50 calls, stops coverage, merges data, and restarts.
     */
    public function flush(): void
    {
        $this->flushCount++;
        if ($this->flushCount % 50 !== 0) {
            return;
        }

        $this->mergeXdebugData();
        xdebug_start_code_coverage(XDEBUG_CC_UNUSED);
    }

    /**
     * Stops Xdebug coverage collection and stores the collected data.
     */
    public function stop(): void
    {
        $this->mergeXdebugData();
    }

    /**
     * Reads and merges Xdebug's accumulated data into $this->data, then stops coverage.
     */
    private function mergeXdebugData(): void
    {
        $newData = xdebug_get_code_coverage();
        xdebug_stop_code_coverage();

        foreach ($newData as $file => $lines) {
            if (!isset($this->data[$file])) {
                $this->data[$file] = $lines;
            } else {
                foreach ($lines as $line => $hit) {
                    $existing = $this->data[$file][$line] ?? 0;
                    $this->data[$file][$line] = max($existing, $hit);
                }
            }
        }
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
        $realSrcPath = realpath($srcPath);
        if ($realSrcPath === false) {
            return [];
        }
        $realSrcPath = rtrim($realSrcPath, '/') . '/';

        $filtered = [];
        foreach ($this->data as $file => $lines) {
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
