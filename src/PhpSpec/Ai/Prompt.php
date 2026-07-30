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

namespace PhpSpec\Ai;

/**
 * @internal
 * One resolved prompt layer: its name, its include-expanded text, and where it
 * came from. Data only; the library builds these, consumers fold or render.
 */
final readonly class Prompt
{
    public const PROJECT = 'project';

    public const SHIPPED = 'shipped';

    /**
     * @param string $name the prompt name (e.g. "commands/generate")
     * @param string $text the include-expanded prompt text
     * @param string $origin one of the PROJECT/SHIPPED constants
     */
    public function __construct(
        public string $name,
        public string $text,
        public string $origin,
    ) {}
}
