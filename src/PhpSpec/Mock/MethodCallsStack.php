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
 * Stack-based tracker that records all method calls made on a test double.
 * Supports push/pop/peek operations and provides query methods for verifying
 * call counts and argument matching during mock assertions.
 */
final class MethodCallsStack
{
    /** @var MockedMethod[] */
    private array $stack = [];

    /**
     * Pushes a method invocation record onto the stack.
     *
     * @param MockedMethod $method the recorded method call
     */
    public function push(MockedMethod $method): void
    {
        $this->stack[] = $method;
    }

    /**
     * Removes and returns the most recent method call from the stack.
     */
    public function pop(): MockedMethod
    {
        return array_pop($this->stack);
    }

    /**
     * Returns the most recent method call without removing it from the stack.
     */
    public function peek(): ?MockedMethod
    {
        return $this->stack[count($this->stack) - 1] ?? null;
    }

    /**
     * Checks whether a call with the given method name and arguments exists in the stack.
     *
     * @param string $method the method name to search for
     * @param array $args the expected arguments for exact matching
     */
    public function exist(string $method, array $args = []): bool
    {
        foreach ($this->stack as $mockedMethod) {
            if ($mockedMethod->method === $method &&
                $args === $mockedMethod->arguments) {
                return true;
            }
        }
        return false;
    }
    /**
     * Decrements the call count for all matching method entries in the stack.
     * Used to undo verification calls that should not count as real invocations.
     *
     * @param MockedMethod $target the method call to un-record
     */
    public function unCall(MockedMethod $target): void
    {
        foreach ($this->stack as $mockedMethod) {
            if ($mockedMethod->method === $target->method &&
                $mockedMethod->arguments === $target->arguments) {
                $mockedMethod->unCall();
            }
        }
    }

    /**
     * Checks whether the given method was called at least once (with a positive call count).
     *
     * @param string $method the method name to check
     */
    public function wasMethodCalled(string $method): bool
    {
        foreach ($this->stack as $mockedMethod) {
            if ($mockedMethod->method === $method && $mockedMethod->wasCalled()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns the total number of times the given method was called across all recordings.
     *
     * @param string $method the method name to count calls for
     */
    public function countCallsTo(string $method): int
    {
        $count = 0;
        foreach ($this->stack as $mockedMethod) {
            if ($mockedMethod->method === $method) {
                $count += $mockedMethod->getTimesCalled();
            }
        }
        return $count;
    }

    /**
     * Counts calls to a method that match a specific argument pattern.
     * If argPattern is null, counts all calls (same as countCallsTo).
     *
     * @param string $method the method name to count calls for
     * @param array|null $argPattern the argument pattern to filter by
     */
    public function countCallsToWithArgs(string $method, ?array $argPattern): int
    {
        if ($argPattern === null) {
            return $this->countCallsTo($method);
        }

        $count = 0;
        foreach ($this->stack as $mockedMethod) {
            if ($mockedMethod->method === $method
                && $mockedMethod->getTimesCalled() > 0
                && ArgumentMatcher::matchArgs($mockedMethod->arguments, $argPattern)
            ) {
                $count += $mockedMethod->getTimesCalled();
            }
        }
        return $count;
    }
}
