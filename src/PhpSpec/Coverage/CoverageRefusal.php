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

namespace PhpSpec\Coverage;

/**
 * @internal
 * Why a run that wanted coverage cannot collect it, or cannot report it once
 * part of it never arrived: the reason, the same command run in a way that
 * would, when there is one, and other ways past it for a reader.
 */
final readonly class CoverageRefusal
{
    /**
     * @param list<string> $options
     */
    public function __construct(
        public string $reason,
        public ?string $remedy = null,
        public array $options = [],
    ) {}
}
