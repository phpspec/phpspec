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

namespace PhpSpec\EventDispatcher\Event;

use Closure;
use PhpSpec\EventDispatcher\Event;
use PhpSpec\Logging\LogDateTime;
use PhpSpec\Logging\Loggable;

/**
 * @internal
 * Dispatched when a context is modified, such as when a let() hook or beforeEach is added.
 */
final readonly class ContextModified implements Event, Loggable
{
    use LogDateTime;
    public const NAME = 'context.modified';

    /**
     * @param string $property the name of the property being modified
     * @param Closure $setter the closure that performs the modification
     */
    public function __construct(private string $property, private Closure $setter) {}

    /** {@inheritdoc} */
    public function getName(): string
    {
        return self::NAME;
    }

    /**
     * Returns the name of the modified property.
     */
    public function getProperty(): string
    {
        return $this->property;
    }

    /**
     * Returns the closure that applies the modification.
     */
    public function getSetter(): Closure
    {
        return $this->setter;
    }

    /** {@inheritdoc} */
    public function getLog(): string
    {
        return $this->getLogNow(
            $this->getName() . ' - ' .
            $this->getProperty(),
        );
    }
}
