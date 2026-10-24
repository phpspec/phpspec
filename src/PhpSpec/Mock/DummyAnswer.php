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
 * @internal
 * What a lenient double answers of a class or interface: a dummy of it.
 */
interface DummyAnswer
{
    /**
     * Makes this what the double answered to a call of the method.
     *
     * @param list<string> $iterating the combinations of types being iterated when it answered a getIterator(), from the outermost
     */
    public function ______PhpSpecAnswering(GeneratedDouble $double, string $method, array $iterating = []): void;

    /**
     * The combinations of types being iterated when it answered.
     *
     * @return list<string>
     */
    public function ______PhpSpecIterating(): array;
}
