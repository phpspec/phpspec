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
 * Contract for listeners that receive all dispatched events regardless of name.
 */
interface Listener
{
    /**
     * Handles a dispatched event.
     *
     * @param Event $event the event to process
     */
    public function listen(Event $event): void;
}
