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

use PhpSpec\ProjectRoot;

/**
 * @internal
 * What the run exercised, line by line.
 *
 * Three answers matter, not two: a line can be covered, executable and never
 * reached, or not executable at all. Without the third, a closing brace or a
 * use statement would read as untested logic, and guard would cry wolf on
 * every change. Xdebug is asked with XDEBUG_CC_UNUSED, so it reports all
 * three, and a file it never loaded at all is answered by the source itself.
 */
final readonly class Coverage
{
    private const NOT_EXECUTABLE = -2;

    /**
     * @param array<string, array<int, int>> $hits project-relative path => line => Xdebug's hit value
     */
    public function __construct(private array $hits) {}

    /**
     * Coverage as Xdebug reports it, with paths made relative to the project
     * so they can be compared with a delta's.
     *
     * @param array<string, array<int, int>> $raw absolute path => line => hit value
     */
    public static function fromHits(array $raw, string $baseDir = '.'): self
    {
        $root = ProjectRoot::at($baseDir);
        $hits = [];

        foreach ($raw as $file => $lines) {
            $hits[$root->relative($file)] = $lines;
        }

        return new self($hits);
    }

    public static function nothing(): self
    {
        return new self([]);
    }

    /**
     * Whether the run reported nothing whatsoever.
     *
     * One file missing from a report means a file nothing exercised. Every
     * file missing means the collection itself failed, and a judgement drawn
     * from it would condemn the whole change on no evidence.
     */
    public function isEmpty(): bool
    {
        return $this->hits === [];
    }

    /**
     * Whether the run ever loaded this file. When it did not, guard has no
     * coverage opinion to offer and reads the source instead.
     */
    public function knows(string $file): bool
    {
        return isset($this->hits[$file]);
    }

    /**
     * Whether an example reached this line.
     */
    public function covers(string $file, int $line): bool
    {
        return ($this->hits[$file][$line] ?? 0) >= 1;
    }

    /**
     * Whether there is anything on this line to reach. A brace, a blank line
     * or a declaration is not logic, and guard has nothing to say about it.
     */
    public function isExecutable(string $file, int $line): bool
    {
        return isset($this->hits[$file][$line]) && $this->hits[$file][$line] !== self::NOT_EXECUTABLE;
    }
}
