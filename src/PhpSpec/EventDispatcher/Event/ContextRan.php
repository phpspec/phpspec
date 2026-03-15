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
use PhpSpec\Specification\Context;

/**
 * Dispatched after a context (describe/context block) finishes running all its examples.
 */
final readonly class ContextRan implements Event, Loggable
{
    use LogDateTime;
    public const NAME = 'context.ran';

    /**
     * @param string $title the context description
     * @param Context $context the context that finished running
     */
    public function __construct(
        private string $title,
        private Context $context,
    ) {}

    /** {@inheritdoc} */
    public function getName(): string
    {
        return self::NAME;
    }

    /**
     * Returns the context description.
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /** {@inheritdoc} */
    public function getLog(): string
    {
        return $this->getLogNow(
            $this->getName() . ' - ' .
            $this->getTitle(),
        );
    }
}
