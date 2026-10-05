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
 * The two sides of a failed call expectation, as data a reader can act on: the
 * call the spec wanted of the double, and the calls the double received.
 */
final readonly class CallComparison
{
    /**
     * @param string $class the type doubled
     * @param string $method the method wanted
     * @param array<mixed>|null $arguments the arguments wanted, matchers included; null when any will do
     * @param string $times how often it was wanted, in words
     * @param MethodCallsStack $calls what the double received
     */
    public function __construct(
        private string $class,
        private string $method,
        private ?array $arguments,
        private string $times,
        private MethodCallsStack $calls,
    ) {}

    /**
     * @return array{method: string, arguments?: list<mixed>, times: string}
     */
    public function wanted(): array
    {
        $wanted = ['method' => $this->name()];

        if ($this->arguments !== null) {
            $wanted['arguments'] = array_map(self::describe(...), array_values($this->arguments));
        }

        $wanted['times'] = $this->times;

        return $wanted;
    }

    /**
     * @return array{method: string, calls: list<array{arguments: list<mixed>}>}
     */
    public function received(): array
    {
        return ['method' => $this->name(), 'calls' => $this->calls->callsTo($this->method)];
    }

    private function name(): string
    {
        return ltrim($this->class, '\\') . '::' . $this->method;
    }

    private static function describe(mixed $argument): mixed
    {
        return $argument instanceof ArgumentMatcher ? $argument->describe() : $argument;
    }
}
