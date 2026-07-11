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
 * A top-level spec block (Context or Example) parsed once and kept as a
 * pristine template. Because run() mutates a block, each run must start from a
 * fresh copy; withWorld() produces that copy with its closure rebound to the
 * run's world, so the parsed file never has to be require'd again.
 */
interface Rebindable extends SpecBlock
{
    /**
     * Returns a fresh, unrun copy of this block with its closure bound to the
     * given world (and, transitively, any closures it defines when it runs).
     *
     * @param Subject $world the world to bind the copy's closure to
     * @return SpecBlock a fresh block ready to run against $world
     */
    public function withWorld(Subject $world): SpecBlock;
}
