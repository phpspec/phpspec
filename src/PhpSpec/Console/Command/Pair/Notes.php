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

namespace PhpSpec\Console\Command\Pair;

/**
 * @internal
 * The session's note book: what the human said alongside their answers, kept
 * where the assistant can read it. Every {@see Chooser} answer reports here, so
 * a note left on a deterministic question (the scaffolding prompts, a run
 * offer) is never lost just because no AI turn was in flight to catch it.
 *
 * A caller that already folds a note into its own reply to the model claims it
 * with {@see take()}; whatever is left over rides the next turn's instruction
 * through {@see brief()}.
 */
final class Notes
{
    /** @var list<array{question: string, accepted: bool, note: string}> notes not yet shown to the model */
    private array $pending = [];

    /** Whether the most recent answer's note is still unclaimed (it is the last pending entry). */
    private bool $latestPending = false;

    /**
     * Records what the human answered and what they said about it. A blank note
     * is nothing to say, so only the answer's timing is remembered: it makes the
     * previous note stale for {@see take()}, which claims the latest one only.
     *
     * @param string $question the question as it was asked, console markup and all
     * @param bool $accepted whether the human accepted
     * @param string $note the note typed alongside the answer, empty when none
     */
    public function record(string $question, bool $accepted, string $note): void
    {
        $this->latestPending = false;
        $note = trim($note);

        if ($note === '') {
            return;
        }

        $this->pending[] = ['question' => self::plain($question), 'accepted' => $accepted, 'note' => $note];
        $this->latestPending = true;
    }

    /**
     * Claims the note left on the most recent answer, for a caller that answers
     * the model itself (a declined tool call, an applied write). Returns an empty
     * string when that answer carried no note, or when it was claimed already.
     */
    public function take(): string
    {
        if (!$this->latestPending) {
            return '';
        }

        $this->latestPending = false;
        $claimed = array_pop($this->pending);

        return $claimed['note'] ?? '';
    }

    /**
     * The instruction with every unclaimed note in front of it, in the human's
     * own voice, and the book emptied. Unchanged when nothing was noted.
     *
     * @param string $instruction the turn's instruction
     * @return string the instruction the model should read
     */
    public function brief(string $instruction): string
    {
        if ($this->pending === []) {
            return $instruction;
        }

        $lines = array_map(
            static fn(array $note): string => sprintf(
                '- I %s "%s" and said: %s',
                $note['accepted'] ? 'accepted' : 'declined',
                $note['question'],
                $note['note'],
            ),
            $this->pending,
        );

        $this->pending = [];
        $this->latestPending = false;

        return "[Notes I left on the choices I just made]\n" . implode("\n", $lines) . "\n\n" . $instruction;
    }

    /**
     * The question as the model should read it: console style tags belong to the
     * screen, not to the conversation.
     */
    private static function plain(string $question): string
    {
        return trim((string) preg_replace('~<[^<>]*>~', '', $question));
    }
}
