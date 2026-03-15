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
 * Marker interface for mock return value wrappers.
 * Implemented by generated method-return classes and LastCallDouble to signal
 * that a value originates from a mock call and can be used with mock expectations.
 */
interface MatchableDouble
{
    /**
     * Returns the test double instance this call was made on.
     */
    public function ______PhpSpecGetDouble(): mixed;

    /**
     * Returns the method name that was called.
     */
    public function ______PhpSpecGetMethod(): string;
}
