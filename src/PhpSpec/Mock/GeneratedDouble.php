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
 * Internal interface implemented by all generated test double classes.
 * Provides methods for stub configuration, call tracking, and class identity.
 *
 * @internal
 */
interface GeneratedDouble
{
    public function ______PhpSpecGetStubbedCalls(): MethodCallsStack;

    public function ______PhpSpecNameOfClassDoubled(): string;

    public function ______PhpSpecBeLenient(): void;

    /**
     * Makes the double a dummy: lenient, and taking no stub or expectation.
     */
    public function ______PhpSpecBeDummy(): void;

    /**
     * Takes a stub or an expectation on the method, which a dummy refuses.
     *
     * @param string $as what the spec did to the method: stubbed, expected or checked
     */
    public function ______PhpSpecArrange(string $method, string $as): void;

    /**
     * The calls that lead to what the double answers to the method, written
     * as the spec writes them.
     */
    public function ______PhpSpecChainTo(string $method): string;

    /**
     * How the spec stubs what the double answers to the method, a dummy's
     * answer being no double the spec can stub.
     */
    public function ______PhpSpecStubAdvice(string $method): string;

    /**
     * @param array<int, mixed>|null $args
     */
    public function ______PhpSpecDeclare(string $method, ?array $args): void;

    /**
     * @param array<int, mixed>|null $args
     */
    public function ______PhpSpecStubReturn(string $method, mixed $value, ?array $args = null): void;

    /**
     * @param array<int, mixed>|null $args
     */
    public function ______PhpSpecStubReturnUsing(string $method, callable $callback, ?array $args = null): void;

    /**
     * @param array<int, mixed>|null $args
     */
    public function ______PhpSpecStubThrow(string $method, string|\Throwable $exception, string $message = '', ?array $args = null): void;
}
