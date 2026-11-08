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
 * The PHP files under the source directories. Coverage engines report only the
 * files coverage was recorded for, so a file with none recorded during an
 * example or scenario is missing from the count rather than counted as
 * uncovered.
 */
final readonly class SourceFiles
{
    /**
     * @param list<string> $directories
     */
    public function __construct(
        private Filesystem $filesystem,
        private array $directories,
        private ProjectRoot $root,
    ) {}

    /**
     * The files with no coverage recorded that coverage does not leave out,
     * named from the project root.
     *
     * @param array<string, mixed> $hits the coverage recorded, by the path the engine gave
     * @return list<string>
     */
    public function unrecorded(array $hits, Exclusions $exclusions): array
    {
        $recorded = array_flip(array_map($this->root->relative(...), array_keys($hits)));
        $unrecorded = [];

        foreach ($this->directories as $directory) {
            foreach ($this->filesUnder($directory) as $file) {
                $name = $this->root->relative($file);

                if (!isset($recorded[$name]) && !$exclusions->leavesOut($file)) {
                    $unrecorded[] = $name;
                }
            }
        }

        $unrecorded = array_values(array_unique($unrecorded));
        sort($unrecorded);

        return $unrecorded;
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
