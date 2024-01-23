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
 * Class InterfaceNotImplementedException holds information about interface
 * not implemented exception
 */
class InterfaceNotImplementedException extends FractureException
{
    public function __construct(
        string $message,
        private mixed $subject,
        private string $interface
    )
    {
    }

    public function getSubject() : mixed
    {
        return $this->subject;
    }

    public function getInterface(): string
    {
        return $this->interface;
    }
}
