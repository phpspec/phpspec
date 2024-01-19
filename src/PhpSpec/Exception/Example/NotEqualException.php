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

namespace PhpSpec\Exception\Example;

/**
 * Class NotEqualException holds information about the non-equal failure exception
 */
class NotEqualException extends FailureException
{
    public function __construct(
        string $message,
        private mixed $expected,
        private mixed $actual
    )
    {
        parent::__construct($message);
    }

    public function getExpected() : mixed
    {
        return $this->expected;
    }

    public function getActual() : mixed
    {
        return $this->actual;
    }

    public function __toString(): string
    {
        return var_export(array($this->expected, $this->actual), true);
    }
}
