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
 * Reads a baseline with the reader that understands it.
 *
 * A baseline records either a commit or the files themselves, and only one
 * reader can make sense of each. Choosing by configuration instead meant a
 * project could ask for one and be handed the other: a commit read as a
 * snapshot finds no recorded files, calls every guarded file new, and fails the
 * run over legacy code the session never touched.
 */
final readonly class RecordedChanges implements Changes
{
    public function __construct(
        private Changes $sinceCommit,
        private Changes $sinceSnapshot,
    ) {}

    public function since(array $baseline): Delta
    {
        return $baseline['kind'] === 'commit'
            ? $this->sinceCommit->since($baseline)
            : $this->sinceSnapshot->since($baseline);
    }
}
