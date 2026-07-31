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
use PhpSpec\Ai\Response;

/**
 * @internal
 * The one conversation a session holds with the model: an ordered message
 * history plus the command it is currently oriented for. Deliberately the one
 * mutable object in the pipeline, because it IS the session state; everything
 * around it stays a value.
 *
 * The system prompt lives in slot 0: `orient()` seats it once per command and
 * re-orienting for another command replaces only that slot, which is what makes
 * a role swap self-healing. `beginTurn()` applies the window policy, so bounded
 * history is the transcript's own concern, not its callers'.
 */
final class Transcript
{
    /** @var Message[] */
    private array $messages = [];

    /** The command name the system slot was composed for, '' when none yet. */
    private string $orientedWith = '';

    public function __construct(private readonly ConversationWindow $window = new ConversationWindow()) {}

    /**
     * Starts a user turn: prunes superseded situations, trims stale tool
     * output, and compacts old turns once the history outgrows the window.
     */
    public function beginTurn(): void
    {
        $this->messages = $this->window->apply($this->messages);
    }

    /**
     * Whether the system slot was composed for this command, so a caller can
     * skip recomposing an unchanged prompt.
     */
    public function isOrientedFor(string $command): bool
    {
        return $this->orientedWith === $command;
    }

    /**
     * Seats the system prompt for a command in slot 0. Re-orienting for the
     * same command is a no-op; a different command replaces only the system
     * slot, keeping the conversation intact across a role swap.
     */
    public function orient(string $command, string $system): void
    {
        if ($this->orientedWith === $command) {
            return;
        }

        $message = Message::system($system);

        if ($this->orientedWith === '') {
            array_unshift($this->messages, $message);
        } else {
            $this->messages[0] = $message;
        }

        $this->orientedWith = $command;
    }

    /**
     * Grounds the turn in the current situation. The marker prefix is what
     * `beginTurn()` recognises next turn, so only the latest snapshot ever
     * reaches the model.
     */
    public function situate(string $report): void
    {
        $this->messages[] = Message::user("[Current situation]\n" . $report);
    }

    /**
     * A user-role message: the human's input, or a report fed to the model
     * (such as an auto-verify result) that should persist as history.
     */
    public function say(string $input): void
    {
        $this->messages[] = Message::user($input);
    }

    /**
     * Records the model's response, tool calls included.
     */
    public function heard(Response $response): void
    {
        $this->messages[] = Message::assistant($response->text, $response->toolCalls ?: null);
    }

    /**
     * Records one tool result, paired to its call by id.
     */
    public function observed(string $toolCallId, mixed $result): void
    {
        $this->messages[] = Message::toolResult($toolCallId, $result);
    }

    /**
     * @return Message[]
     */
    public function messages(): array
    {
        return $this->messages;
    }
}
