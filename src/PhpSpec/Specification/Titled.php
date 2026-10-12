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

namespace PhpSpec\Specification;

/**
 * @internal
 * A block named by a title its siblings may share.
 */
interface Titled
{
    /**
     * The title as the spec wrote it, before any number told it apart.
     */
    public function writtenTitle(): string;

    /**
     * Numbers this block among the siblings that share its title: the second
     * is #2, the third #3.
     */
    public function numberAmongNamesakes(int $ordinal): void;
}
