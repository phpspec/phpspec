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

namespace PhpSpec\Console\Command\Run;

use PhpSpec\Filesystem;
use PhpSpec\RealFilesystem;

/**
 * @internal
 * Finds the most recently modified files by modification time, so `next` can
 * point at the last-touched feature or source without reaching for git. The
 * newest `.feature` also doubles as the deterministic "do features exist?" gate.
 */
final class RecencyScanner
{
    private readonly Filesystem $filesystem;

    /**
     * @param Filesystem|null $filesystem filesystem abstraction for testability
     */
    public function __construct(?Filesystem $filesystem = null)
    {
        $this->filesystem = $filesystem ?? new RealFilesystem();
    }

    /**
     * The path of the most recently modified `.feature` file under the given
     * directory, or null when the directory is absent or holds no feature.
     */
    public function mostRecentFeature(string $featuresDir): ?string
    {
        return $this->mostRecentByExtension($featuresDir, '.feature');
    }

    /**
     * The path of the most recently modified `.php` file under the given source
     * directory, or null when the directory is absent or holds no source.
     */
    public function mostRecentSource(string $srcDir): ?string
    {
        return $this->mostRecentByExtension($srcDir, '.php');
    }

    private function mostRecentByExtension(string $dir, string $extension): ?string
    {
        if (!$this->filesystem->exists($dir) || !$this->filesystem->isDir($dir)) {
            return null;
        }

        $mostRecent = null;
        $mostRecentMtime = -1;

        foreach ($this->filesInTree($dir, $extension) as $file) {
            $mtime = $this->filesystem->mtime($file);
            if ($mtime > $mostRecentMtime) {
                $mostRecentMtime = $mtime;
                $mostRecent = $file;
            }
        }

        return $mostRecent;
    }

    /**
     * Every file under a directory (recursively) whose name ends in $extension.
     *
     * @return list<string>
     */
    private function filesInTree(string $dir, string $extension): array
    {
        $files = [];

        foreach ($this->filesystem->scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $dir . '/' . $entry;
            if ($this->filesystem->isDir($path)) {
                $files = array_merge($files, $this->filesInTree($path, $extension));

                continue;
            }

            if (str_ends_with($entry, $extension)) {
                $files[] = $path;
            }
        }

        return $files;
    }
}
