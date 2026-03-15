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

/**
 * Dispatched when a deferred match closure is created, to be evaluated after the example runs.
 */
final readonly class MatchCreated implements Event
{
    public const NAME = 'match.created';

    /**
     * @param Closure $match the deferred match closure that produces a MatchResult when invoked
     */
    public function __construct(private Closure $match) {}

    /** {@inheritdoc} */
    public function getName(): string
    {
        return self::NAME;
    }

    /**
     * Returns the deferred match closure.
     */
    public function getMatch(): Closure
    {
        return $this->match;
    }
}
