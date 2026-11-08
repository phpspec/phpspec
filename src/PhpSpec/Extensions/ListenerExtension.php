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

namespace PhpSpec\Extensions;

use PhpSpec\Result\Outcome;
use PhpSpec\Result\State;

/**
 * Base class for listener extensions: told when the suite begins and ends,
 * and of each example and each scenario as an Outcome, by how it ended, once
 * its spec file or feature has run. Override only what you need.
 */
abstract class ListenerExtension
{
    public function beforeSuite(): void {}

    public function afterSuite(): void {}

    /**
     * An example or a scenario has run; tells the method for how it ended.
     */
    public function completed(Outcome $outcome): void
    {
        match ($outcome->state) {
            State::Passed, State::Risky => $this->onPass($outcome),
            State::Failed, State::Undefined => $this->onFail($outcome),
            State::Errored => $this->onError($outcome),
            State::Pending => $this->onPending($outcome),
            State::Skipped => $this->onSkipped($outcome),
        };
    }

    /**
     * An example or scenario that passed, or an example that checked nothing.
     */
    public function onPass(Outcome $outcome): void {}

    /**
     * An example or scenario that failed, or a scenario with a step no
     * definition matches.
     */
    public function onFail(Outcome $outcome): void {}

    public function onError(Outcome $outcome): void {}

    public function onPending(Outcome $outcome): void {}

    public function onSkipped(Outcome $outcome): void {}
}
