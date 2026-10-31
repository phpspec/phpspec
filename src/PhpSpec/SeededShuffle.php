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

namespace PhpSpec;

/**
 * @internal
 * The order a random run puts things in: the same seed orders the same items
 * the same way, in one process or dealt across several.
 */
final readonly class SeededShuffle
{
    public function __construct(private int $seed) {}

    /**
     * @template T
     * @param array<T> $items
     * @return list<T>
     */
    public function of(array $items): array
    {
        $items = array_values($items);
        mt_srand($this->seed);
        shuffle($items);

        return $items;
    }
}
