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
 * Class PropertyNotFoundException holds information about property not found
 * exceptions
 */
class PropertyNotFoundException extends FractureException
{
    public function __construct(
        string $message,
        private object $subject,
        private string $property
    )
    {
        parent::__construct($message);
    }

    public function getSubject() : object
    {
        return $this->subject;
    }

    public function getProperty(): string
    {
        return $this->property;
    }
}
