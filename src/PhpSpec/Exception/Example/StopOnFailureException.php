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

use Exception;

/**
 * Class StopOnFailureException holds information about stop on failure exception
 */
class StopOnFailureException extends ExampleException
{
    public function __construct(
        string $message = "",
        int $code = 0,
        ?Exception $previous = null,
        private int $result = 0)
    {
        parent::__construct($message, $code, $previous);
    }

    public function getResult() : int
    {
        return $this->result;
    }
}
