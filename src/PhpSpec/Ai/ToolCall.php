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
 * Represents a tool call requested by the LLM.
 */
final readonly class ToolCall
{
    /**
     * @param string $id unique identifier for this tool call
     * @param string $name the tool name to invoke
     * @param array<string, mixed> $arguments the arguments to pass to the tool
     */
    public function __construct(
        public string $id,
        public string $name,
        public array $arguments,
    ) {}
}
