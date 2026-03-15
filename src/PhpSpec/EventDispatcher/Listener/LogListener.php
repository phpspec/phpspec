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

namespace PhpSpec\EventDispatcher\Listener;

use PhpSpec\EventDispatcher\Event;
use PhpSpec\EventDispatcher\Listener;
use PhpSpec\Logging\Loggable;

/**
 * Outputs timestamped log lines for all Loggable events, useful for debugging spec execution.
 */
final class LogListener implements Listener
{
    /**
     * Prints the log message of any Loggable event to stdout.
     *
     * @param Event $event the dispatched event to inspect
     */
    public function listen(Event $event): void
    {
        if ($event instanceof Loggable) {
            echo $event->getLog() . "\n";
        }
    }
}
