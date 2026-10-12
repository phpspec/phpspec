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

namespace PhpSpec\Specification;

/**
 * @internal
 * How many examples a spec file declares on each of its lines, counted as
 * its blocks are collected and before any filter or target prunes them, so
 * an example a loop declares beside others is known for one even in a run
 * that keeps it alone.
 */
final class DeclaredLines
{
    /** @var array<string, int> examples declared, keyed by file and line */
    private array $counts = [];

    public function note(string $file, int $line): void
    {
        $key = $file . ':' . $line;
        $this->counts[$key] = ($this->counts[$key] ?? 0) + 1;
    }

    public function shared(string $file, int $line): bool
    {
        return ($this->counts[$file . ':' . $line] ?? 0) > 1;
    }
}
