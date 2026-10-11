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

/**
 * @internal
 * A step of a plan, as the checklist names it and as the progress line says
 * it is under way ("Introducing DiscountPolicy").
 */
final readonly class PlannedStep
{
    public function __construct(
        public string $title,
        public string $doing,
    ) {}
}
