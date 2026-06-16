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

use Exception;

/**
 * @internal
 * Tracks the most recent mock method call for scalar-return-type bridging.
 * When a doubled method returns a scalar (not a MatchableDouble wrapper),
 * this object is stored in Expectation::$lastDouble so that allow() can
 * retrieve it and configure stubs via toReturn() or toThrow().
 */
final class LastCallDouble implements MatchableDouble
{
    /**
     * @param object $double the test double instance that was called
     * @param string $method the method name that was invoked
     * @param array<int, mixed>|null $args the arguments from the setup call (null = catch-all)
     */
    public function __construct(
        private readonly object $double,
        private string $method,
        private ?array $args = null,
    ) {}

    /**
     * Returns the test double instance this call was made on.
     */
    public function ______PhpSpecGetDouble(): object
    {
        return $this->double;
    }

    /**
     * Returns the method name that was called.
     */
    public function ______PhpSpecGetMethod(): string
    {
        return $this->method;
    }

    /**
     * Returns the captured arguments from the setup call.
     *
     * @return array<int, mixed>|null
     */
    public function ______PhpSpecGetArgs(): ?array
    {
        return $this->args;
    }

    /**
     * Configures the tracked method to return a specific value on subsequent calls.
     * Removes the verification call from the calls stack to avoid false counts.
     *
     * @param mixed $value the value to return
     */
    public function toReturn(mixed $value): void
    {
        if ($this->double instanceof GeneratedDouble) {
            $this->double->______PhpSpecStubReturn($this->method, $value, $this->args);
            $this->double->______PhpSpecGetStubbedCalls()->pop();
        }
    }

    /**
     * Configures the tracked method to compute its return value using a callback.
     * The callback receives the same arguments as the mocked method call.
     * Removes the verification call from the calls stack to avoid false counts.
     *
     * @param callable $callback receives the method arguments and returns the stub value
     */
    public function toReturnUsing(callable $callback): void
    {
        if ($this->double instanceof GeneratedDouble) {
            $this->double->______PhpSpecStubReturnUsing($this->method, $callback, $this->args);
            $this->double->______PhpSpecGetStubbedCalls()->pop();
        }
    }

    /**
     * Configures the tracked method to throw an exception on subsequent calls.
     * Removes the verification call from the calls stack to avoid false counts.
     *
     * @param class-string<Exception> $exceptionClass the exception class to throw
     * @param string $message the exception message
     */
    public function toThrow(string $exceptionClass, string $message = ''): void
    {
        if ($this->double instanceof GeneratedDouble) {
            $this->double->______PhpSpecStubThrow($this->method, $exceptionClass, $message, $this->args);
            $this->double->______PhpSpecGetStubbedCalls()->pop();
        }
    }
}
