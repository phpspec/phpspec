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

namespace PhpSpec\Mock;

use PhpSpec\EventDispatcher\DispatcherRegistry;
use PhpSpec\EventDispatcher\Event\ExpectationStarted;
use PhpSpec\EventDispatcher\Event\MatchCreated;
use PhpSpec\Result\MatchResult;

/**
 * Fluent call count expectation returned by toBeCalled().
 * Supports once(), twice(), never(), exactly(N)->times(),
 * atLeast(N)->times(), atMost(N)->times().
 *
 * Registers a single deferred MatchCreated event whose closure captures
 * $this, so mutations from fluent methods are visible at eval time.
 */
final class CallCountExpectation
{
    private string $constraint = 'atLeast';
    private int $count = 1;

    /**
     * @param string $methodName the name of the method being verified
     * @param MethodCallsStack $calls the call stack tracking all invocations on the double
     * @param string $class the fully qualified class name of the doubled class
     * @param string $file the spec file where the expectation was created
     * @param int $line the line number in the spec file
     * @param bool $negated whether the expectation is negated (not()->toBeCalled())
     * @param array|null $argPattern argument pattern to filter counted calls, or null for all calls
     * @param MatchableDouble $mockSubject the mock subject being verified
     */
    public function __construct(
        private readonly string $methodName,
        private readonly MethodCallsStack $calls,
        private readonly string $class,
        private readonly string $file,
        private readonly int $line,
        private readonly bool $negated,
        private readonly ?array $argPattern,
        private readonly MatchableDouble $mockSubject,
    ) {
        $d = DispatcherRegistry::get();
        $d->dispatch(new ExpectationStarted(), ExpectationStarted::NAME);
        $d->dispatch(new MatchCreated(function () {
            return $this->evaluate();
        }), MatchCreated::NAME);
    }

    /**
     * Asserts the method was called exactly once.
     */
    public function once(): self
    {
        $this->constraint = 'exactly';
        $this->count = 1;
        return $this;
    }

    /**
     * Asserts the method was called exactly twice.
     */
    public function twice(): self
    {
        $this->constraint = 'exactly';
        $this->count = 2;
        return $this;
    }

    /**
     * Asserts the method was never called.
     */
    public function never(): self
    {
        $this->constraint = 'exactly';
        $this->count = 0;
        return $this;
    }

    /**
     * Begins an exactly(N)->times() constraint.
     */
    public function exactly(int $n): CallCountIntermediate
    {
        return new CallCountIntermediate($this, 'exactly', $n);
    }

    /**
     * Begins an atLeast(N)->times() constraint.
     */
    public function atLeast(int $n): CallCountIntermediate
    {
        return new CallCountIntermediate($this, 'atLeast', $n);
    }

    /**
     * Begins an atMost(N)->times() constraint.
     */
    public function atMost(int $n): CallCountIntermediate
    {
        return new CallCountIntermediate($this, 'atMost', $n);
    }

    /**
     * Called by CallCountIntermediate::times() to finalize the constraint.
     */
    public function setConstraint(string $constraint, int $count): void
    {
        $this->constraint = $constraint;
        $this->count = $count;
    }

    /**
     * Evaluates the call count constraint and returns a pass/fail MatchResult.
     */
    private function evaluate(): MatchResult
    {
        $actual = $this->calls->countCallsToWithArgs($this->methodName, $this->argPattern);

        $pass = match ($this->constraint) {
            'exactly' => $actual === $this->count,
            'atLeast' => $actual >= $this->count,
            'atMost' => $actual <= $this->count,
            default => false,
        };

        if ($this->negated) {
            $pass = !$pass;
        }

        if ($pass) {
            return MatchResult::passed();
        }

        $label = match ($this->constraint) {
            'exactly' => "exactly $this->count",
            'atLeast' => "at least $this->count",
            'atMost' => "at most $this->count",
        };

        $not = $this->negated ? ' not' : '';
        $message = "Expected $this->class::{$this->methodName}()$not to be called $label time(s), but was called $actual time(s)";

        return MatchResult::failed(
            $this->mockSubject,
            $this->count,
            $message,
            $this->file,
            $this->line,
        );
    }
}
