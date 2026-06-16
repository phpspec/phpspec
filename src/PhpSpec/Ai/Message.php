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
 * Represents a message in an AI conversation.
 */
final readonly class Message
{
    /**
     * @param Role $role the message role
     * @param string|mixed $content the message content
     * @param ToolCall[]|null $toolCalls tool calls attached to assistant messages
     * @param string|null $toolCallId the tool call ID for tool result messages
     */
    public function __construct(
        public Role $role,
        public mixed $content,
        public ?array $toolCalls = null,
        public ?string $toolCallId = null,
    ) {}

    /**
     * Creates a system message.
     */
    public static function system(string $content): self
    {
        return new self(Role::System, $content);
    }

    /**
     * Creates a user message.
     */
    public static function user(string $content): self
    {
        return new self(Role::User, $content);
    }

    /**
     * Creates an assistant message with optional tool calls.
     *
     * @param ToolCall[]|null $toolCalls
     */
    public static function assistant(string $content, ?array $toolCalls = null): self
    {
        return new self(Role::Assistant, $content, $toolCalls);
    }

    /**
     * Creates a tool result message.
     */
    public static function toolResult(string $toolCallId, mixed $content): self
    {
        return new self(Role::Tool, $content, null, $toolCallId);
    }
}
