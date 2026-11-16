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

namespace PhpSpec\Ai\Agent;

use PhpSpec\Ai\Message;
use PhpSpec\Ai\Role;

/**
 * @internal
 * Keeps a pair session's conversation within its budget. Until the budget is
 * reached the history is left exactly as it is, so every request extends the
 * one before it and the provider can cache it. Past the budget the
 * conversation starts afresh: the system prompt in force, a summary of the
 * turns it drops, and the recent turns verbatim, cut on a user message so no
 * tool_use/tool_result pair is split.
 */
final readonly class ConversationWindow
{
    /**
     * @param int $recentMessages how many trailing messages are always kept verbatim
     * @param int $compactThreshold history length past which the oldest turns are folded
     * @param int $maxContextTokens estimated-token budget that also triggers compaction
     */
    public function __construct(
        private int $recentMessages = 16,
        private int $compactThreshold = 40,
        private int $maxContextTokens = 48000,
    ) {}

    /**
     * The history as the next request should carry it: unchanged within the
     * budget, started afresh past it.
     *
     * @param Message[] $messages
     * @return Message[]
     */
    public function apply(array $messages): array
    {
        if (!$this->needsCompaction($messages)) {
            return $messages;
        }

        $cut = $this->firstUserBoundary($messages, max(1, count($messages) - $this->recentMessages));
        if ($cut === null) {
            return $messages;
        }

        $dropped = array_slice($messages, 0, $cut);
        $spoken = array_values(array_filter($dropped, static fn(Message $message): bool => $message->role !== Role::System));
        if ($spoken === []) {
            return $messages;
        }

        $inForce = array_values(array_filter($dropped, static fn(Message $message): bool => $message->role === Role::System));

        return array_merge(array_slice($inForce, -1), [$this->summarise($spoken)], array_slice($messages, $cut));
    }

    /**
     * Whether history has grown past the message-count or estimated-token budget.
     *
     * @param Message[] $messages
     */
    private function needsCompaction(array $messages): bool
    {
        return count($messages) > $this->compactThreshold
            || $this->estimatedTokens($messages) > $this->maxContextTokens;
    }

    /**
     * A cheap chars-per-token estimate of the whole history's size.
     *
     * @param Message[] $messages
     */
    private function estimatedTokens(array $messages): int
    {
        $chars = 0;
        foreach ($messages as $message) {
            $chars += is_string($message->content) ? strlen($message->content) : 0;
        }

        return intdiv($chars, 4);
    }

    /**
     * The index of the first user message at or after $from, or null when the
     * tail holds none (in which case compaction is skipped this round).
     *
     * @param Message[] $messages
     */
    private function firstUserBoundary(array $messages, int $from): ?int
    {
        for ($index = $from; $index < count($messages); $index++) {
            if ($messages[$index]->role === Role::User) {
                return $index;
            }
        }

        return null;
    }

    /**
     * Builds the compact "earlier in this session" summary from the dropped
     * messages: how much was folded, the artifacts written, and the last suite
     * state seen — enough for the model to keep the thread without the detail.
     *
     * @param Message[] $dropped
     */
    private function summarise(array $dropped): Message
    {
        $artifacts = [];
        $lastState = null;

        foreach ($dropped as $message) {
            $content = is_string($message->content) ? $message->content : '';

            if (preg_match('/(?:written to|created at|added to|File updated:|Spec skeleton for) .+/', $content, $matches)) {
                $artifacts[$matches[0]] = true;
            }

            if (preg_match('/SUITE: [^\n]+/', $content, $matches)) {
                $lastState = $matches[0];
            }
        }

        $lines = [
            '[Earlier in this session]',
            sprintf('%d earlier messages were compacted to save context.', count($dropped)),
        ];

        if ($artifacts !== []) {
            $lines[] = 'Work done:';
            foreach (array_slice(array_keys($artifacts), -8) as $artifact) {
                $lines[] = '  - ' . $artifact;
            }
        }

        if ($lastState !== null) {
            $lines[] = 'Last suite state seen: ' . $lastState;
        }

        return Message::user(implode("\n", $lines));
    }
}
