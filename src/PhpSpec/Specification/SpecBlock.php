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

use PhpSpec\Results;

/**
 * @internal
 * Contract for executable spec blocks (Suite, Specification, Context, Example).
 */
interface SpecBlock
{
    /**
     * Executes this block and returns its results.
     *
     * @return Results
     */
    public function run(): Results;
}
