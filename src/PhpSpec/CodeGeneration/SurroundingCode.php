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

namespace PhpSpec\CodeGeneration;

use PhpSpec\Filesystem;
use PhpSpec\RealFilesystem;

/**
 * @internal
 * Extracts source code lines surrounding a given line number for error reporting context.
 * Returns a window of lines before and after the target line, preserving original line numbers.
 */
final class SurroundingCode
{
    /** @var Filesystem */
    private Filesystem $filesystem;

    /**
     * @param string $file the absolute path to the source file
     * @param int $line the target line number (1-based)
     * @param int $before number of lines to include before the target line
     * @param int $after number of lines to include after the target line
     * @param Filesystem|null $filesystem filesystem abstraction for testability
     */
    public function __construct(
        private readonly string $file,
        private readonly int $line,
        private readonly int $before = 3,
        private readonly int $after = 3,
        ?Filesystem $filesystem = null,
    ) {
        $this->filesystem = $filesystem ?? new RealFilesystem();
    }

    /**
     * Returns an associative array of line numbers to source code lines surrounding the target line.
     *
     * @return array<int, string> line numbers mapped to their source code content
     */
    public function toArray(): array
    {
        if ($this->file === '') {
            return [];
        }

        $lines = $this->filesystem->readLines($this->file);

        // Build 1-based indexed array
        /** @var array<int, string> $code */
        $code = [];
        foreach ($lines as $i => $lineContent) {
            $code[$i + 1] = $lineContent;
        }

        $line = $this->line;
        $start = max(1, $line - $this->before);
        $end = $line + $this->after;

        /** @var array<int, string> $result */
        $result = [];
        foreach ($code as $num => $content) {
            if ($num >= $start && $num <= $end) {
                $result[$num] = $content;
            }
        }

        return $result;
    }
}
