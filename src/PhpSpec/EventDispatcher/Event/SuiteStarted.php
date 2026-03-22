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
 * @internal
 * Dispatched when the suite begins execution.
 */
final readonly class SuiteStarted implements Event, Loggable
{
    use LogDateTime;
    public const NAME = 'suite.started';

    /** {@inheritdoc} */
    public function getName(): string
    {
        return self::NAME;
    }

    /** {@inheritdoc} */
    public function getLog(): string
    {
        return $this->getLogNow($this->getName());
    }
}
