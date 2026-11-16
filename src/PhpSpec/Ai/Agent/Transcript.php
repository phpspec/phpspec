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
 * The conversation only grows, so every request extends the one before it.
 * `orient()` seats the first system prompt in slot 0, and a later command's
 * prompt is said after the conversation so far. `beginTurn()` applies the
 * window, so bounded history is the transcript's own concern, not its callers'.
 */
final class Transcript
{
    /** @var Message[] */
    private array $messages = [];

    /** The command name the system slot was composed for, '' when none yet. */
    private string $orientedWith = '';

    /**
     * @param ConversationWindow|null $window keeps the conversation within budget; none when the provider does that itself
     */
    public function __construct(private readonly ?ConversationWindow $window = new ConversationWindow()) {}

    /**
     * Starts a user turn, starting the conversation afresh once it outgrows
     * the window.
     */
    public function beginTurn(): void
    {
        if ($this->window !== null) {
            $this->messages = $this->window->apply($this->messages);
        }
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
     * Seats the system prompt for a command: the first in slot 0, a later one
     * after the conversation so far, so a role swap changes nothing earlier.
     * Re-orienting for the same command is a no-op.
     */
    public function orient(string $command, string $system): void
    {
        if ($this->orientedWith === $command) {
            return;
        }

        if ($this->orientedWith === '') {
            array_unshift($this->messages, Message::system($system));
        } else {
            $this->messages[] = Message::system($system);
        }

        $this->orientedWith = $command;
    }

    /**
     * Grounds the turn in the current situation, after the earlier ones.
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
        $this->messages[] = $response->toMessage();
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
