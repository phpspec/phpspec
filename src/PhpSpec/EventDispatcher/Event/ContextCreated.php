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
 * @internal
 * Dispatched when a context scope (describe/context block) is created during spec loading.
 */
final readonly class ContextCreated implements Event, Loggable
{
    use LogDateTime;
    public const NAME = 'context.created';

    /**
     * @param string $title the context description
     * @param Context $context the newly created context
     */
    public function __construct(private string $title, private Context $context) {}

    /** {@inheritdoc} */
    public function getName(): string
    {
        return self::NAME;
    }

    /**
     * Returns the context that was created.
     */
    public function getContext(): Context
    {
        return $this->context;
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
