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
 * Value object capturing the outcome of an AI-driven refactoring attempt.
 */
final readonly class RefactorResult
{
    /**
     * @param bool $success whether the refactoring was applied and specs still pass
     * @param string $technique the name of the refactoring technique applied
     * @param string $description a brief explanation of what was changed and why
     * @param string $diff the formatted diff of source file changes
     * @param string $specOutput the spec runner output after applying the refactoring
     */
    public function __construct(
        public bool $success,
        public string $technique,
        public string $description,
        public string $diff,
        public string $specOutput,
    ) {}
}
