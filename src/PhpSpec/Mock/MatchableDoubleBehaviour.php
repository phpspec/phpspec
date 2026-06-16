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
 *
 * Provides the MatchableDouble implementation for generated wrapper classes.
 * Used by eval'd classes that wrap mock method return values to enable
 * stub configuration (toReturn, toThrow, toReturnUsing).
 *
 * Classes using this trait must set $______phpspec_mockedObject and
 * $______phpspec_methodName in their constructor.
 */
trait MatchableDoubleBehaviour
{
    /** @var object The parent test double instance */
    private object $______phpspec_mockedObject;

    /** @var string The method name this wrapper represents */
    private string $______phpspec_methodName;

    public function ______PhpSpecGetDouble(): mixed
    {
        return $this->______phpspec_mockedObject;
    }

    public function ______PhpSpecGetMethod(): string
    {
        return $this->______phpspec_methodName;
    }

    public function toReturn(mixed $value): void
    {
        $this->______phpspec_mockedObject->______PhpSpecStubReturn($this->______phpspec_methodName, $value);
        $this->______phpspec_mockedObject->______PhpSpecGetStubbedCalls()->pop();
    }

    public function toReturnUsing(callable $callback): void
    {
        $this->______phpspec_mockedObject->______PhpSpecStubReturnUsing($this->______phpspec_methodName, $callback);
        $this->______phpspec_mockedObject->______PhpSpecGetStubbedCalls()->pop();
    }

    public function toThrow(string $exceptionClass, string $message = ''): void
    {
        $this->______phpspec_mockedObject->______PhpSpecStubThrow($this->______phpspec_methodName, $exceptionClass, $message);
        $this->______phpspec_mockedObject->______PhpSpecGetStubbedCalls()->pop();
    }
}
