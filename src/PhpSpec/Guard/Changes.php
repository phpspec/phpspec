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

namespace PhpSpec\Guard;

/**
 * @internal
 * What changed since the session started. Git answers it when there is a
 * repository; a snapshot of the files answers it when there is not, and guard
 * asks the same question either way.
 */
interface Changes
{
    /**
     * The lines this session added or changed, since what the baseline
     * recorded.
     *
     * @param array{kind: string, commit?: string, files?: array<string, string>} $baseline
     */
    public function since(array $baseline): Delta;
}
