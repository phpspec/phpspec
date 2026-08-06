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

namespace PhpSpec\Guard;

/**
 * @internal
 * The only way guard talks to git. Behind a seam so a spec can answer for a
 * repository without one existing, and so the day guard needs another question
 * there is one place that knows how to ask.
 */
interface Git
{
    /**
     * Whether the project is a git repository at all. When it is not, guard
     * falls back to comparing a snapshot of the files themselves.
     */
    public function isRepository(): bool;

    /**
     * The commit the working tree is on, or null when there is none: a
     * repository with no commits yet has nothing to be a baseline.
     */
    public function head(): ?string;
}
