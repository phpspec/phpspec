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

namespace PhpSpec;

/**
 * Contract for result containers at every level of the spec hierarchy
 * (suite, specification, context, example).
 */
interface Results
{
    /**
     * Returns child result objects.
     *
     * @return array<Results>
     */
    public function getResults(): array;
}
