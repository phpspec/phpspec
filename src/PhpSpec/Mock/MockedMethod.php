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

/**
 * @internal
 * Records a single method invocation on a test double, tracking the subject,
 * method name, arguments, and a mutable call count used for verification.
 */
final class MockedMethod
{
    /** @var int Number of times this method has been called (mutable for unCall/call adjustments) */
    private int $timesCalled;

    /**
     * @param mixed $subject the test double instance the method was called on
     * @param string $method the invoked method name
     * @param array<int, mixed> $arguments the arguments passed to the method
     */
    public function __construct(
        public readonly mixed $subject,
        public readonly string $method,
        public readonly array $arguments = [],
    ) {
        $this->timesCalled = 1;
    }

    /**
     * Decrements the call count by one, used to undo verification-triggered calls.
     */
    public function unCall(): void
    {
        $this->timesCalled--;
    }

    /**
     * Creates a MockedMethod with a call count of zero, representing an expected
     * but not-yet-invoked method call.
     *
     * @param object $subject the test double instance
     * @param string $method the method name
     * @param array<int, mixed> $args the expected arguments
     */
    public static function withoutCall(object $subject, string $method, array $args = []): self
    {
        $method = new self($subject, $method, $args);
        $method->timesCalled = 0;
        return $method;
    }

    /**
     * Checks whether this method was called exactly the specified number of times.
     *
     * @param int $times the expected call count (defaults to 1)
     */
    public function wasCalled(int $times = 1): bool
    {
        return $this->timesCalled === $times;
    }

    /**
     * Increments the call count by one.
     */
    public function call(): void
    {
        $this->timesCalled++;
    }

    /**
     * Returns the current call count for this method invocation.
     */
    public function getTimesCalled(): int
    {
        return $this->timesCalled;
    }
}
