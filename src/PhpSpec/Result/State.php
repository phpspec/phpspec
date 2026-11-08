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

namespace PhpSpec\Result;

/**
 * How an example, a step or a scenario ended, named as the run counts it.
 */
enum State: string
{
    case Passed = 'passed';
    case Failed = 'failed';
    case Errored = 'errored';
    case Pending = 'pending';
    case Skipped = 'skipped';
    case Risky = 'risky';
    case Undefined = 'undefined';

    /**
     * Whether ending this way fails the run: an undefined step was never
     * checked, which is not a pass; pending work is deferred on purpose, and is.
     */
    public function failsTheRun(): bool
    {
        return match ($this) {
            self::Failed, self::Errored, self::Undefined => true,
            default => false,
        };
    }
}
