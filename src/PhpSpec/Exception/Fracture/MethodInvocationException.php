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

namespace PhpSpec\Exception\Fracture;

/**
 * Class MethodInvocationException holds information about method invocation
 * exceptions
 */
abstract class MethodInvocationException extends FractureException
{
    public function __construct(
        string $message,
        private object $subject,
        private string $method,
        private array $arguments = array()
    )
    {
        parent::__construct($message);
    }

    public function getSubject() : object
    {
        return $this->subject;
    }

    public function getMethodName(): string
    {
        return $this->method;
    }


    public function getArguments(): array
    {
        return $this->arguments;
    }
}
