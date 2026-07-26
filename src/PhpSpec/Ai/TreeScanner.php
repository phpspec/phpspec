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

namespace PhpSpec\Ai;

use PhpSpec\Filesystem;
use PhpSpec\RealFilesystem;

/**
 * @internal
 * The one project-tree scanner for AI grounding: an indented, sorted listing
 * of the files under a directory, to a caller-chosen depth. One-shot commands
 * ground shallow to stay lean; the pair loop scans deeper for its cached
 * system prompt.
 */
final class TreeScanner
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
     * The listing for a directory, empty when it is missing or not a directory.
     *
     * @param string $absPath the absolute directory path
     * @param int $maxDepth how many levels to descend
     */
    public function scan(string $absPath, int $maxDepth): string
    {
        return $this->walk($absPath, $maxDepth, 0);
    }

    /**
     * Recursively lists entries up to the depth limit, directories marked with
     * a trailing slash and children indented beneath them.
     */
    private function walk(string $absPath, int $maxDepth, int $depth): string
    {
        if ($depth >= $maxDepth || !$this->filesystem->exists($absPath) || !$this->filesystem->isDir($absPath)) {
            return '';
        }

        $entries = $this->filesystem->scandir($absPath);
        $entries = array_filter($entries, fn($entry) => $entry !== '.' && $entry !== '..');
        sort($entries);

        $lines = [];
        $indent = str_repeat('  ', $depth);

        foreach ($entries as $entry) {
            $full = $absPath . '/' . $entry;
            if ($this->filesystem->isDir($full)) {
                $lines[] = "$indent$entry/";
                $sub = $this->walk($full, $maxDepth, $depth + 1);
                if ($sub !== '') {
                    $lines[] = $sub;
                }
            } else {
                $lines[] = "$indent$entry";
            }
        }

        return implode("\n", $lines);
    }
}
