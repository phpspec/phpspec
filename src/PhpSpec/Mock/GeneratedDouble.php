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
    public function ______PhpSpecStubThrow(string $method, string $exceptionClass, string $message = '', ?array $args = null): void;
}
