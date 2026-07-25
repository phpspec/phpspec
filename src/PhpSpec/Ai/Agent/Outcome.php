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

namespace PhpSpec\Ai\Agent;

/**
 * @internal
 * What one `do()` call produced: the step it resolved, the proposals to
 * confirm, and any prose for the human. Presenters render this and nothing
 * else, so every command surface (interactive diff, plain advice, agent JSON)
 * is a rendering of the same value.
 */
final readonly class Outcome
{
    /**
     * @param Step|null $step the resolved step, null when nothing determined one
     * @param list<Proposal> $proposals the file changes to confirm and apply
     * @param string $prose text for the human: advice, or the reason nothing was proposed
     */
    public function __construct(
        public ?Step $step,
        public array $proposals = [],
        public string $prose = '',
    ) {}
}
