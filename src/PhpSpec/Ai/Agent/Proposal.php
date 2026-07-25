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
 * One proposed file change. Tools only ever return these; nothing writes to
 * disk until the presenter has confirmed and the Writer applies. The origin
 * names the tool (or deterministic path) that produced it, for the capture log.
 */
final readonly class Proposal
{
    /**
     * @param string $path the project-relative path of the file
     * @param string $old the current content, empty for a new file
     * @param string $new the complete proposed content
     * @param bool $isNew whether the file does not exist yet
     * @param string $origin the tool or deterministic route that produced this
     */
    public function __construct(
        public string $path,
        public string $old,
        public string $new,
        public bool $isNew,
        public string $origin = '',
    ) {}
}
