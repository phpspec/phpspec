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
    /** @var GeneratedDouble The parent test double instance */
    private GeneratedDouble $______phpspec_mockedObject;

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

    public function toReturn(mixed $value): static
    {
        $this->______PhpSpecAsWritten()->toReturn($value);

        return $this;
    }

    public function andReturn(mixed $value): static
    {
        return $this->toReturn($value);
    }

    public function toReturnUsing(callable $callback): static
    {
        $this->______PhpSpecAsWritten()->toReturnUsing($callback);

        return $this;
    }

    public function toThrow(string|\Throwable $exception, string $message = ''): static
    {
        $this->______PhpSpecAsWritten()->toThrow($exception, $message);

        return $this;
    }

    /**
     * The refusal of a call on this answer, which is not for use: held when
     * the code under spec made it, so catching it hides nothing.
     *
     * @param array<int, mixed> $arguments
     * @param array{file?: string, line?: int} $frame
     */
    private function ______PhpSpecUsed(string $class, string $method, array $arguments, array $frame): UnexpectedCallException
    {
        $refusal = UnexpectedCallException::onAnswer($class, $method, $arguments, $frame['file'] ?? '', $frame['line'] ?? 0, $this->______phpspec_mockedObject->______PhpSpecChainTo($this->______phpspec_methodName));

        if (!ArrangingCode::includes($frame['file'] ?? '')) {
            HeldCalls::hold($this, $method, $arguments, $refusal);
        }

        return $refusal;
    }

    private function ______PhpSpecAsWritten(): LastCallDouble
    {
        $method = $this->______phpspec_methodName;

        return new LastCallDouble($this->______phpspec_mockedObject, $method, $this->______phpspec_mockedObject->______PhpSpecGetStubbedCalls()->writtenFor($method));
    }
}
