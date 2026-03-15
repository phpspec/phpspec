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

use PhpSpec\EventDispatcher\Event;
use PhpSpec\Logging\LogDateTime;
use PhpSpec\Logging\Loggable;

/**
 * Dispatched when a specification file finishes execution.
 */
final readonly class SpecificationFinished implements Event, Loggable
{
    use LogDateTime;
    public const NAME = 'specification.finished';

    /**
     * @param string $title the specification description
     */
    public function __construct(private string $title) {}

    /** {@inheritdoc} */
    public function getName(): string
    {
        return self::NAME;
    }

    /**
     * Returns the specification description.
     *
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /** {@inheritdoc} */
    public function getLog(): string
    {
        return $this->getLogNow($this->getName() . ' - ' . $this->title);
    }
}
