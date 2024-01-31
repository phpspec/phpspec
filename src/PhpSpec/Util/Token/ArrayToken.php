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

final class ArrayToken extends Token
{
    /** @param array{0:int,1:string,2:int} $phpToken */
    public function __construct(
        private array $phpToken)
    {
    }

    public function asString() : string
    {
        return $this->phpToken[1];
    }

    public function hasType(int $tokenType) : bool
    {
        return $this->phpToken[0] === $tokenType;
    }

    /** @param list<int> $types */
    public function isInTypes(array $tokenTypes) : bool
    {
        return in_array($this->phpToken[0], $tokenTypes, true);
    }

    function getLine(): int
    {
        return $this->phpToken[2];
    }
}
