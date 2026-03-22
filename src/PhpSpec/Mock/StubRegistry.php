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
 * Resolves which stub entry matches a given method call based on argument patterns.
 * Entries are stored as [$argPattern, $data] pairs; null argPattern is a catch-all.
 * Later-registered entries are checked first (prepend semantics).
 */
final class StubRegistry
{
    /**
     * Finds the first matching stub entry for a method call.
     *
     * @param string $method the method name
     * @param array<int, mixed> $actualArgs the actual arguments passed to the method
     * @param array<string, list<array{0: array<int, mixed>|null, 1: mixed}>> $stubbedReturns keyed by method name, each value is array of [$argPattern, $value]
     * @param array<string, list<array{0: array<int, mixed>|null, 1: callable}>> $stubbedReturnCallbacks keyed by method name, each value is array of [$argPattern, callable]
     * @param array<string, list<array{0: array<int, mixed>|null, 1: array{class: string, message: string}}>> $stubbedThrows keyed by method name, each value is array of [$argPattern, ['class'=>..., 'message'=>...]]
     * @return array{type: string, data: mixed}|null ['type' => 'value'|'callback'|'throw', 'data' => mixed] or null if no match
     */
    public static function findMatch(
        string $method,
        array $actualArgs,
        array $stubbedReturns,
        array $stubbedReturnCallbacks,
        array $stubbedThrows,
    ): ?array {
        // Check throws first (highest priority for matching)
        if (isset($stubbedThrows[$method])) {
            foreach ($stubbedThrows[$method] as [$argPattern, $data]) {
                if (self::argsMatch($actualArgs, $argPattern)) {
                    return ['type' => 'throw', 'data' => $data];
                }
            }
        }

        // Check callbacks
        if (isset($stubbedReturnCallbacks[$method])) {
            foreach ($stubbedReturnCallbacks[$method] as [$argPattern, $callback]) {
                if (self::argsMatch($actualArgs, $argPattern)) {
                    return ['type' => 'callback', 'data' => $callback];
                }
            }
        }

        // Check values
        if (isset($stubbedReturns[$method])) {
            foreach ($stubbedReturns[$method] as [$argPattern, $value]) {
                if (self::argsMatch($actualArgs, $argPattern)) {
                    return ['type' => 'value', 'data' => $value];
                }
            }
        }

        return null;
    }

    /**
     * Returns true if actual arguments match the pattern, or if the pattern is null (catch-all).
     *
     * @param array<int, mixed> $actualArgs
     * @param array<int, mixed>|null $argPattern
     */
    private static function argsMatch(array $actualArgs, ?array $argPattern): bool
    {
        if ($argPattern === null) {
            return true; // catch-all
        }
        return ArgumentMatcher::matchArgs($actualArgs, $argPattern);
    }
}
