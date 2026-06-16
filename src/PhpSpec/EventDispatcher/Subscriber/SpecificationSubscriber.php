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

namespace PhpSpec\EventDispatcher\Subscriber;

use PhpSpec\EventDispatcher\Event\SpecificationStarted;
use PhpSpec\EventDispatcher\Subscriber;
use PhpSpec\Specification;

/**
 * @internal
 * Resets the Specification state when a new specification file starts loading.
 *
 * Ensures each specification file begins with a clean slate by listening for the
 * SpecificationStarted event and resetting the subscribed Specification.
 */
final readonly class SpecificationSubscriber implements Subscriber
{
    /**
     * @param Specification $subscribed the specification instance to reset on new file load
     */
    public function __construct(private Specification $subscribed) {}

    /** {@inheritdoc} */
    public function getSubscribedEvents(): array
    {
        return [
            SpecificationStarted::NAME => 'onSpecificationStarted',
        ];
    }

    /**
     * Resets the specification state when a new specification file starts loading.
     *
     * @param mixed $event the specification started event
     */
    public function onSpecificationStarted($event): void
    {
        $this->subscribed->reset();
    }
}
