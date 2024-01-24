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

namespace PhpSpec\Util;

use PhpSpec\Util\Token\ArrayToken;
use PhpSpec\Util\Token\StringToken;

abstract class Token
{
    /**
     * @param string|array{0:int,1:string,2:int} $phpToken
     */
    public static function fromPhpToken(string|array $phpToken) : static
    {
        return is_string($phpToken) ? new StringToken($phpToken) : new ArrayToken($phpToken);
    }

    public function equals(string $string) : bool
    {
        return $this->asString() === $string;
    }

    public static function getAll(string $code) : array
    {
        return array_map(static::fromPhpToken(...), token_get_all($code));
    }

    abstract function asString() : string;

    abstract function hasType(int $tokenType) : bool;

    /** @param list<int> $types */
    abstract function isInTypes(array $tokenTypes) : bool;

    abstract function getLine() : ?int;
}
