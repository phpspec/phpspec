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

use PhpSpec\Filesystem;
use PhpSpec\ProjectRoot;

/**
 * @internal
 * The PHP files under a source directory. Coverage engines report only the
 * files a run loaded, so a file no spec loads is missing from the count
 * rather than counted as uncovered.
 */
final readonly class SourceFiles
{
    public function __construct(
        private Filesystem $filesystem,
        private string $directory,
        private ProjectRoot $root,
    ) {}

    /**
     * The files the run never loaded and coverage does not leave out, named
     * from the project root.
     *
     * @param array<string, mixed> $hits what the run loaded, by the path the engine gave
     * @return list<string>
     */
    public function unloaded(array $hits, Exclusions $exclusions): array
    {
        $loaded = array_flip(array_map($this->root->relative(...), array_keys($hits)));
        $unloaded = [];

        foreach ($this->filesUnder($this->directory) as $file) {
            $name = $this->root->relative($file);

            if (!isset($loaded[$name]) && !$exclusions->leavesOut($file)) {
                $unloaded[] = $name;
            }
        }

        sort($unloaded);

        return $unloaded;
    }

    /**
     * @return list<string>
     */
    private function filesUnder(string $directory): array
    {
        if (!$this->filesystem->isDir($directory)) {
            return [];
        }

        $files = [];

        foreach ($this->filesystem->scandir($directory) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = rtrim($directory, '/\\') . '/' . $entry;

            if ($this->filesystem->isDir($path)) {
                $files = [...$files, ...$this->filesUnder($path)];
            } elseif (str_ends_with($entry, '.php')) {
                $files[] = $path;
            }
        }

        return $files;
    }
}
