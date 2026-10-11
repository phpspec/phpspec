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
 * The titles a block's children were given: a child whose title a sibling of
 * its kind already has is numbered, so every name a formatter prints, and
 * every id an agent keeps, tells the two apart. The first keeps its title, so
 * adding a namesake never renames it.
 */
final class Namesakes
{
    /** @var array<string, int> how many children of each kind carry each title */
    private array $counted = [];

    public function number(SpecBlock $block): void
    {
        if (!$block instanceof Titled) {
            return;
        }

        $key = $block::class . "\n" . $block->writtenTitle();
        $this->counted[$key] = ($this->counted[$key] ?? 0) + 1;

        if ($this->counted[$key] > 1) {
            $block->numberAmongNamesakes($this->counted[$key]);
        }
    }
}
