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

namespace PhpSpec\Report\Formatter\Agent;

/**
 * @internal
 * The agent formatter's output contract in one place: the schema version stamped
 * on every event as "v", and the event-type names. Bump V on any breaking change
 * to the emitted shape so a consuming agent can detect it.
 */
final class Schema
{
    public const V = 1;

    public const EVENT_RUN_STARTED = 'run_started';
    public const EVENT_SUMMARY = 'summary';
}
