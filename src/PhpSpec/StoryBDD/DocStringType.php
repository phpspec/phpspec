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

namespace PhpSpec\StoryBDD;

use Attribute;

/**
 * Marks a function that turns a doc string for the step parameter named after
 * the type.
 */
#[Attribute(Attribute::TARGET_FUNCTION)]
final readonly class DocStringType
{
    public function __construct(public string $name) {}
}
