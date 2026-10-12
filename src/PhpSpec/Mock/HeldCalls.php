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
 * The calls a double received that nothing declared, until a
 * toHaveBeenCalled() checks them; one still unchecked when the example ends
 * is refused.
 */
final class HeldCalls
{
    /** @var list<array{double: object, method: string, arguments: array<int|string, mixed>, refusal: UnexpectedCallException}> */
    private static array $held = [];

    /**
     * @param array<int|string, mixed> $arguments
     */
    public static function hold(object $double, string $method, array $arguments, UnexpectedCallException $refusal): void
    {
        self::$held[] = ['double' => $double, 'method' => $method, 'arguments' => $arguments, 'refusal' => $refusal];
    }

    /**
     * Takes back the calls to a method a check has spoken about, those its
     * arguments match, or all of them when it names none.
     *
     * @param array<int|string, mixed>|null $pattern
     */
    public static function claim(object $double, string $method, ?array $pattern): void
    {
        self::$held = array_values(array_filter(
            self::$held,
            static fn(array $call): bool => $call['double'] !== $double
                || $call['method'] !== $method
                || ($pattern !== null && !ArgumentMatcher::matchArgs($call['arguments'], $pattern)),
        ));
    }

    /**
     * The refusal of the first call no check claimed, and nothing held after.
     */
    public static function take(): ?UnexpectedCallException
    {
        $first = self::$held[0]['refusal'] ?? null;
        self::$held = [];

        return $first;
    }

    public static function forget(): void
    {
        self::$held = [];
    }
}
