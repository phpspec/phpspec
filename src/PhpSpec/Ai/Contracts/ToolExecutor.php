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

namespace PhpSpec\Ai\Contracts;

use PhpSpec\Ai\Response;
use PhpSpec\Ai\ToolCall;

/**
 * @internal
 * A live session's half of the agent loop: what the pipeline consults to
 * advertise, execute, and settle tools inside one conversational turn. Without
 * one, the pipeline stays propose-only (tool calls become Proposals and the
 * caller applies them); with one, tool calls run against the session (confirm
 * gates, spec runs, questions) and their results feed back to the model.
 *
 * A round starts at `advertised()` and settles with `observations()` and
 * `turnComplete()`, so an implementation snapshots its per-round state there
 * instead of being asked for it.
 */
interface ToolExecutor
{
    /**
     * Resets the per-turn state before a user turn's first round.
     */
    public function beginTurn(): void;

    /**
     * The tools to advertise this round, serialised for the provider: the
     * session's declared surface filtered by its turn state (a spent offer, a
     * written artifact), plus any extension tools. The executor owns the whole
     * surface; the pipeline only forwards what it answers.
     *
     * @return list<array{name: string, description: string, input_schema: array<string, mixed>}>
     */
    public function advertised(): array;

    /**
     * Executes one tool call against the session and returns the result the
     * model should observe.
     */
    public function execute(ToolCall $call): mixed;

    /**
     * Reports to feed the model after the round's calls ran (an auto-verify
     * result), each becoming a user-role message.
     *
     * @return list<string>
     */
    public function observations(): array;

    /**
     * The hand-back line when the session says this turn must end (the one
     * artifact landed and its wrap-up is spent), or null to keep looping.
     */
    public function turnComplete(Response $response): ?string;

    /**
     * The structured next-step suggestion registered this turn, or null.
     *
     * @return array<string, string>|null
     */
    public function lastSuggestion(): ?array;
}
