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

namespace PhpSpec\Specification;

use PhpSpec\EventDispatcher\DispatcherRegistry;
use PhpSpec\EventDispatcher\Event\ExpectationStarted;
use PhpSpec\EventDispatcher\Event\MatchCreated;
use PhpSpec\Result\MatchResult;

/**
 * @internal
 * Bridges the Expectation API with the event system. Creates and dispatches
 * MatchCreated events that carry deferred match evaluation closures.
 */
final readonly class EventfulExpectation
{
    /**
     * @param mixed $subject value being asserted
     * @param string $file source file of the expect() call
     * @param int $line source line of the expect() call
     */
    public function __construct(
        private mixed $subject,
        private string $file,
        private int $line,
    ) {}

    /**
     * Dispatches ExpectationStarted and MatchCreated events with a deferred matcher closure.
     *
     * @param \Closure $match matcher callback receiving the subject
     * @param string $message failure message template
     * @param string|null $fakeExpression PHP expression for --fake code generation
     * @param string|null $matcher the matcher method that produced the expectation (e.g. "toBe")
     * @param bool $negated whether the matcher was negated
     * @param string|null $relation the matcher's relation phrase for the expected/actual pair (e.g. "to be contained in")
     * @param mixed ...$values format values for the failure message
     * @return void
     */
    public function createMatchEvent(\Closure $match, string $message, ?string $fakeExpression = null, ?string $matcher = null, bool $negated = false, ?string $relation = null, ...$values): void
    {
        DispatcherRegistry::dispatcher()->dispatch(new ExpectationStarted(), ExpectationStarted::NAME);
        DispatcherRegistry::dispatcher()->dispatch(new MatchCreated(fn() => match (true) {
            $match($this->subject) => MatchResult::passed(),
            default => MatchResult::failed(
                $this->subject,
                $values[1] ?? null,
                Expectation::formatMessage($message, ...$values),
                $this->file,
                $this->line,
                $fakeExpression,
                $matcher,
                $negated,
                $relation,
            )
        }), MatchCreated::NAME);
    }
}
