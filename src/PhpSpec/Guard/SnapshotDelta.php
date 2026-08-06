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

use PhpSpec\Console\Command\Refactor\Diff;
use PhpSpec\Filesystem;

/**
 * @internal
 * What changed, for a project git cannot answer for: the guarded files as they
 * stand against what the baseline recorded them holding.
 *
 * The configuration calls this "mtime" because that is what a reader
 * recognises, but a timestamp only says a file was written, not that anything
 * changed in it: the content is what decides, and the line numbers come from
 * diffing it against what was recorded.
 */
final readonly class SnapshotDelta implements Changes
{
    public function __construct(
        private Filesystem $filesystem,
        private Scope $scope,
        private string $baseDir = '.',
    ) {}

    public function since(array $baseline): Delta
    {
        $recorded = $baseline['files'] ?? [];
        $changed = [];
        foreach ($this->guarded() as $file => $content) {
            $before = $recorded[$file] ?? null;

            if ($before === null) {
                // Not recorded, so written since: new from first line to last.
                $changed[$file] = range(1, max(1, count(explode("\n", $content))));

                continue;
            }

            if ($before === $content) {
                continue;
            }

            $changed[$file] = $this->addedLines((string) $before, $content);
        }

        return $this->scope->bound(Delta::of($changed));
    }

    /**
     * The line numbers the change added, as the file now stands.
     *
     * @return list<int>
     */
    private function addedLines(string $before, string $now): array
    {
        $lines = [];
        foreach (Diff::compute(explode("\n", $before), explode("\n", $now)) as $entry) {
            if ($entry['type'] === '+') {
                $lines[] = $entry['line'];
            }
        }

        return $lines;
    }

    /**
     * Every guarded file, with what it holds now.
     *
     * @return array<string, string>
     */
    private function guarded(): array
    {
        $files = [];
        foreach ($this->scope->roots() as $root) {
            $this->collect(rtrim($this->baseDir, '/') . '/' . ltrim($root, './'), $files);
        }

        return $files;
    }

    /**
     * @param array<string, string> $files
     */
    private function collect(string $dir, array &$files): void
    {
        if (!$this->filesystem->isDir($dir)) {
            return;
        }

        foreach ($this->filesystem->scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $dir . '/' . $entry;
            if ($this->filesystem->isDir($path)) {
                $this->collect($path, $files);
            } elseif (str_ends_with($entry, '.php')) {
                $files[$this->relative($path)] = $this->filesystem->read($path);
            }
        }
    }

    private function relative(string $file): string
    {
        $prefix = rtrim($this->baseDir, '/') . '/';

        return str_starts_with($file, $prefix) ? substr($file, strlen($prefix)) : $file;
    }
}
