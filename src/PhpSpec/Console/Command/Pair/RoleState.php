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

namespace PhpSpec\Console\Command\Pair;

/**
 * @internal
 * The current pairing role, held mutably and shared by reference between the
 * dispatcher and the AI assistant (like the shared Chooser) so a /swap is seen
 * by both. Defaults to the human driving and the AI navigating.
 */
final class RoleState
{
    /**
     * @param PairRole $role the starting role (defaults to the human driving)
     */
    public function __construct(private PairRole $role = PairRole::HumanDrives) {}

    /**
     * The role in effect right now.
     */
    public function current(): PairRole
    {
        return $this->role;
    }

    /**
     * Swaps to the other role and returns it.
     */
    public function swap(): PairRole
    {
        return $this->role = $this->role->swapped();
    }
}
