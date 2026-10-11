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
 * What carrying out a plan left: the steps kept, the files they wrote, and
 * why it stopped short, when it did.
 */
final readonly class Carried
{
    /**
     * @param list<string> $kept the titles of the steps kept
     * @param list<string> $files the files the kept steps wrote, project-relative
     * @param string $stopped why it stopped short, empty when every step was kept
     */
    public function __construct(
        public int $exitCode,
        public array $kept,
        public array $files,
        public string $stopped = '',
    ) {}
}
