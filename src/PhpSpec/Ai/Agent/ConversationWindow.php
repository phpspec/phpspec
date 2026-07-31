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
 * Keeps a pair session's message history bounded, fresh, and focused so a long
 * session never bloats, costs, or loses the plot. It drops superseded grounding,
 * trims stale tool output, and — past a size threshold — folds the oldest turns
 * into a single compact "earlier in this session" summary, always preserving the
 * system prompt, the recent turns verbatim, and clean tool_use/tool_result
 * pairing. Grounding (Slice B) and auto-verify (Slice C) make fresh state
 * authoritative, so old history is largely redundant and safe to compact.
 */
final readonly class ConversationWindow
{
    /**
     * @param int $recentMessages how many trailing messages are always kept verbatim
     * @param int $compactThreshold history length past which the oldest turns are folded
     * @param int $maxContextTokens estimated-token budget that also triggers compaction
     * @param int $toolResultMax characters kept of a stale tool result before trimming
     */
    public function __construct(
        private int $recentMessages = 16,
        private int $compactThreshold = 40,
        private int $maxContextTokens = 48000,
        private int $toolResultMax = 400,
    ) {}

    /**
     * Applies the full window policy to a message history and returns the result.
     * Intended to run once per turn, before fresh grounding and input are added.
     *
     * @param Message[] $messages
     * @return Message[]
     */
    public function apply(array $messages): array
    {
        $messages = $this->pruneSupersededSituations($messages);
        $messages = $this->trimStaleToolOutput($messages);

        return $this->compact($messages);
    }

    /**
     * Removes every "[Current situation]" grounding message — a fresh one is
     * injected each turn, so the previous snapshots are pure noise.
     *
     * @param Message[] $messages
     * @return Message[]
     */
    private function pruneSupersededSituations(array $messages): array
    {
        return array_values(array_filter(
            $messages,
            fn(Message $message) => !$this->isSituation($message),
        ));
    }

    /**
     * Whether a message is a "[Current situation]" grounding message.
     */
    private function isSituation(Message $message): bool
    {
        return $message->role === Role::User
            && is_string($message->content)
            && str_starts_with($message->content, '[Current situation]');
    }

    /**
     * Shrinks the content of tool results that have fallen out of the recent
     * window — a stale read_file or run_specs dump is superseded by fresh reads
     * and auto-verify, so only a short marker is kept. The message itself stays
     * so its tool_use/tool_result pairing is never broken.
     *
     * @param Message[] $messages
     * @return Message[]
     */
    private function trimStaleToolOutput(array $messages): array
    {
        $cutoff = count($messages) - $this->recentMessages;
        if ($cutoff <= 0) {
            return $messages;
        }

        foreach ($messages as $index => $message) {
            if ($index >= $cutoff) {
                break;
            }

            if ($message->role === Role::Tool && is_string($message->content) && strlen($message->content) > $this->toolResultMax) {
                $messages[$index] = new Message(Role::Tool, '[earlier tool result trimmed]', null, $message->toolCallId);
            }
        }

        return $messages;
    }

    /**
     * Folds the oldest turns into a single summary once history grows past the
     * threshold, keeping the system message, the summary, and the recent turns.
     * The cut lands on a user-message boundary so no tool_use/tool_result pair is
     * split and the retained tail never begins with an orphan tool result.
     *
     * @param Message[] $messages
     * @return Message[]
     */
    private function compact(array $messages): array
    {
        if (!$this->needsCompaction($messages)) {
            return $messages;
        }

        $system = $messages[0];
        $rest = array_slice($messages, 1);

        $cut = $this->firstUserBoundary($rest, max(0, count($rest) - $this->recentMessages));
        if ($cut === null || $cut === 0) {
            return $messages;
        }

        $dropped = array_slice($rest, 0, $cut);
        $retained = array_slice($rest, $cut);

        return array_merge([$system, $this->summarise($dropped)], $retained);
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
     * @param Message[] $rest
     */
    private function firstUserBoundary(array $rest, int $from): ?int
    {
        for ($index = $from; $index < count($rest); $index++) {
            if ($rest[$index]->role === Role::User) {
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
