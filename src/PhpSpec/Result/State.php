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

namespace PhpSpec\Result;

use Closure;

/**
 * Static accumulator that collects deferred match closures and evaluates them into an ExampleResult.
 *
 * Acts as a lightweight alternative to the event-based result collection, storing closures
 * and running them on demand via the run() method.
 */
final class State
{
    /** @var Closure[] Deferred match closures awaiting evaluation */
    private static array $results = [];

    /**
     * Adds a deferred match closure to the accumulator.
     *
     * @param Closure $result the closure that produces a MatchResult
     */
    public static function add(Closure $result): void
    {
        self::$results[] = $result;
    }

    /**
     * Clears all accumulated match closures.
     */
    public static function reset(): void
    {
        self::$results = [];
    }

    /**
     * Evaluates all accumulated match closures and returns an ExampleResult.
     *
     * @param string $title the example description for the resulting ExampleResult
     */
    public static function run(string $title): ExampleResult
    {
        $state = [];
        foreach (self::$results as $matchResults) {
            $state[] = $matchResults();
        }
        return new ExampleResult($title, $state);
    }
}
