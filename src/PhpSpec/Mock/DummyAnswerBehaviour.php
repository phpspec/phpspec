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

use LogicException;

/**
 * @internal
 *
 * What a lenient double answers of a class or interface: a dummy of it,
 * answering with defaults at any depth. A spec that stubs it or expects a
 * call on it depends on what it answers, which a mock answering the first
 * call of the chain stands in for instead.
 *
 * Used by eval'd DummyAnswer classes beside MatchableDoubleBehaviour.
 */
trait DummyAnswerBehaviour
{
    /** @var string The calls that led to this answer, from the double the spec holds */
    private string $______phpspec_chain;

    /** @var string How the spec stubs what this answer stands for */
    private string $______phpspec_advice;

    public function ______PhpSpecAnswering(GeneratedDouble $double, string $method): void
    {
        $this->______phpspec_mockedObject = $double;
        $this->______phpspec_methodName = $method;
        $this->______phpspec_chain = $double->______PhpSpecChainTo($method);
        $this->______phpspec_advice = $double->______PhpSpecStubAdvice($method);
        $this->______PhpSpecBeLenient();
    }

    public function ______PhpSpecChainTo(string $method): string
    {
        return $this->______phpspec_chain . '->' . $method . '()';
    }

    public function ______PhpSpecStubAdvice(string $method): string
    {
        return $this->______phpspec_advice;
    }

    public function ______PhpSpecArrange(string $method, string $as): void
    {
        throw new LogicException(sprintf(
            '%s::%s() was %s on what dummy %s answered. %s',
            ltrim($this->______PhpSpecNameOfClassDoubled(), '\\'),
            $method,
            $as,
            $this->______phpspec_chain,
            $this->______phpspec_advice,
        ));
    }

    protected function ______PhpSpecWayOut(string $method): string
    {
        return ' ' . $this->______phpspec_advice;
    }
}
