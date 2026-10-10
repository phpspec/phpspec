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
 * What a refactor run works on: the class, its source file and its spec, both
 * absolute, and the method it focuses on when it was given one.
 */
final readonly class RefactorTarget
{
    public function __construct(
        public string $fqcn,
        public string $sourceFile,
        public string $specFile,
        public ?string $method = null,
    ) {}
}
