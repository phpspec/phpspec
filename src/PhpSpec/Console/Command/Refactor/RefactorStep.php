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

namespace PhpSpec\Console\Command\Refactor;

use PhpSpec\Ai\Agent\Proposal;

/**
 * @internal
 * One baby step of a plan: every file it writes, in full, and whether it is
 * meant to leave its specs red until a later step, as a spec written first
 * is.
 */
final readonly class RefactorStep
{
    /**
     * @param list<Proposal> $files
     */
    public function __construct(
        public string $title,
        public array $files,
        public bool $red,
    ) {}
}
