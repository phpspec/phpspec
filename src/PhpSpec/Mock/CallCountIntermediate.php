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

namespace PhpSpec\Mock;

/**
 * Bridge object for the fluent call count API.
 * Holds a constraint type and count, and finalizes via times().
 */
final readonly class CallCountIntermediate
{
    /**
     * @param CallCountExpectation $parent the parent expectation to apply the constraint to
     * @param string $constraint the constraint type ('exactly', 'atLeast', or 'atMost')
     * @param int $count the expected call count
     */
    public function __construct(
        private CallCountExpectation $parent,
        private string $constraint,
        private int $count,
    ) {}

    /**
     * Finalizes the constraint by applying it to the parent expectation.
     */
    public function times(): CallCountExpectation
    {
        $this->parent->setConstraint($this->constraint, $this->count);
        return $this->parent;
    }
}
