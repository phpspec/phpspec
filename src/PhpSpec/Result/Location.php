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

namespace PhpSpec\Result;

use PhpSpec\ProjectRoot;
use Stringable;

/**
 * A line in a file: where an example or a scenario is written, or where it
 * went wrong.
 */
final readonly class Location implements Stringable
{
    private function __construct(
        public string $file,
        public int $line,
    ) {}

    /**
     * The line in the file, or nowhere when either is missing: a result rebuilt
     * from a report that carried no site says so with a blank file or a line of
     * zero, which must not read as a place.
     */
    public static function at(?string $file, ?int $line): ?self
    {
        if ($file === null || $file === '' || $line === null || $line < 1) {
            return null;
        }

        return new self($file, $line);
    }

    /**
     * The same line, its file named from the project root.
     */
    public function relativeTo(ProjectRoot $root): self
    {
        return new self($root->relative($this->file), $this->line);
    }

    public function __toString(): string
    {
        return $this->file . ':' . $this->line;
    }
}
