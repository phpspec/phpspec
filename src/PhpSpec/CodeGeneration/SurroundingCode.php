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

        $code = [null, ...$this->filesystem->readLines($this->file)];

        // lines start at 1, get rid of index 0
        unset($code[0]);

        $line = $this->line;
        $start = $line - $this->before - 1;
        $numberOfLines = $this->before + $this->after + 1;

        // a negative start would send us to the end of the file
        if ($line < ($this->before + 1)) {
            $start = 0;
        }

        return array_slice($code, $start, $numberOfLines, true);
    }
}
