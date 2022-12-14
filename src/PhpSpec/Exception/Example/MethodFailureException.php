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

class MethodFailureException extends NotEqualException
{
    public function __construct(string $message, mixed $expected, mixed $actual, private mixed $subject, private string $method)
    {
        parent::__construct($message, $expected, $actual);
    }

    public function getSubject(): mixed
    {
        return $this->subject;
    }

    public function getMethod(): string
    {
        return $this->method;
    }
}
