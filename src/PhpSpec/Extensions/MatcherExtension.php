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

namespace PhpSpec\Extensions;

/**
 * Base class for matcher extensions. Subclasses must implement getName(),
 * match(), and failureMessage(). negatedFailureMessage() has a default.
 */
abstract class MatcherExtension
{
    /**
     * Returns the matcher name (e.g. 'toBeValidJson').
     *
     * @return string
     */
    abstract public function getName(): string;

    /**
     * Evaluates the match against the actual value.
     *
     * @param mixed    $actual The value being tested
     * @param mixed ...$args   Additional arguments passed to the matcher
     *
     * @return bool True if the match succeeds, false otherwise
     */
    abstract public function match(mixed $actual, mixed ...$args): bool;

    /**
     * Returns the failure message when the match fails.
     *
     * @param mixed $actual The value that did not match
     *
     * @return string
     */
    abstract public function failureMessage(mixed $actual): string;

    /**
     * Returns the failure message for negated matches.
     *
     * @param mixed $actual The value that unexpectedly matched
     *
     * @return string
     */
    public function negatedFailureMessage(mixed $actual): string
    {
        return 'Expected not: ' . $this->failureMessage($actual);
    }
}
