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
 * Represents an LLM chat completion response.
 */
final readonly class Response
{
    /**
     * @param string $text the text content of the response
     * @param ToolCall[] $toolCalls tool calls requested by the LLM
     * @param int $outputTokens how many tokens the model wrote, 0 when the provider does not say
     */
    public function __construct(
        public string $text,
        public array $toolCalls = [],
        public int $outputTokens = 0,
    ) {}

    /**
     * Whether the response contains tool calls.
     */
    public function hasToolCalls(): bool
    {
        return $this->toolCalls !== [];
    }
}
