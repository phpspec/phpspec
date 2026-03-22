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

namespace PhpSpec\EventDispatcher;

/**
 * @internal
 * Contract for subscribers that declare interest in specific named events.
 */
interface Subscriber
{
    /**
     * Returns a map of event names to method name(s) on this subscriber.
     *
     * @return array<string, string|string[]> event name => method name or array of method names
     */
    public function getSubscribedEvents(): array;
}
