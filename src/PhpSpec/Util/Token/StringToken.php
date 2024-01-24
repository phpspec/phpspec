<?php

/*
 * This file is part of PhpSpec, A php toolset to drive emergent
 * design by specification.
 *
 * (c) Marcello Duarte <marcello.duarte@gmail.com>
 * (c) Konstantin Kudryashov <ever.zet@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpSpec\Util\Token;

use PhpSpec\Util\Token;

final class StringToken extends Token
{
    public function __construct(
        private string $string
    )
    {
    }

    public function asString() : string
    {
        return $this->string;
    }

    public function hasType(int $tokenType) : bool
    {
        return false;
    }

    /** @param list<int> $types */
    public function isInTypes(array $tokenTypes) : bool
    {
        return false;
    }
}
