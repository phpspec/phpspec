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

namespace PhpSpec\Report\Formatter\Agent;

/**
 * @internal
 * The error that ended the process: a compile or parse error no catch block ever
 * sees. A plain value, so the formatter never has to know how PHP reports one.
 */
final readonly class Fatal
{
    /**
     * @param string $message PHP's own description of the error
     * @param string|null $file the file it fired in, when known
     * @param int|null $line the line it fired on, when known
     */
    public function __construct(
        public string $message,
        public ?string $file = null,
        public ?int $line = null,
    ) {}
}
