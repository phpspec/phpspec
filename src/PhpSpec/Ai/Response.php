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
     * @param array<string, array<mixed>> $replay what the provider needs back with this answer on the next request, keyed by provider name
     */
    public function __construct(
        public string $text,
        public array $toolCalls = [],
        public int $outputTokens = 0,
        public array $replay = [],
    ) {}

    /**
     * Whether the response contains tool calls.
     */
    public function hasToolCalls(): bool
    {
        return $this->toolCalls !== [];
    }

    /**
     * The assistant message that continues the conversation, carrying what
     * the provider needs back.
     */
    public function toMessage(): Message
    {
        return Message::assistant($this->text, $this->toolCalls ?: null, $this->replay);
    }

    /**
     * The answer as a recording keeps it, in the shape a replay reads back.
     *
     * @return array{text: string, tool_calls: list<array{id: string, name: string, arguments: array<string, mixed>}>, replay?: array<string, array<mixed>>}
     */
    public function recorded(): array
    {
        $recorded = [
            'text' => $this->text,
            'tool_calls' => array_values(array_map(
                static fn(ToolCall $call): array => ['id' => $call->id, 'name' => $call->name, 'arguments' => $call->arguments],
                $this->toolCalls,
            )),
        ];

        if ($this->replay !== []) {
            $recorded['replay'] = $this->replay;
        }

        return $recorded;
    }
}
