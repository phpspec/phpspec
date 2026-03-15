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
 * Contract for containers that accept child spec blocks (Specification, Context).
 */
interface ExampleRegistry
{
    /**
     * Registers a child spec block.
     *
     * @param SpecBlock $example child block to add
     * @return void
     */
    public function addSpecBlock(SpecBlock $example): void;
}
