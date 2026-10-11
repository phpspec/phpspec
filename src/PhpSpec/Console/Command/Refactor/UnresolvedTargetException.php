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

use RuntimeException;

/**
 * @internal
 * A refactor target that cannot be worked on, saying why and what to do
 * instead.
 */
final class UnresolvedTargetException extends RuntimeException
{
    public function __construct(
        private readonly string $problem,
        private readonly string $remedy = '',
    ) {
        parent::__construct(trim($problem . ' ' . $remedy));
    }

    public function problem(): string
    {
        return $this->problem;
    }

    public function remedy(): string
    {
        return $this->remedy;
    }
}
