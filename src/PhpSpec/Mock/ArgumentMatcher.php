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

use Closure;

/**
 * Flexible argument matcher for use with toBeCalledWith() assertions.
 * Provides factory methods for common matching strategies: any value,
 * type checking, custom callback predicates, and more.
 */
final readonly class ArgumentMatcher
{
    /**
     * @param string $type the matching strategy
     * @param mixed $value the constraint value
     */
    private function __construct(private string $type, private mixed $value = null) {}

    /**
     * Creates a matcher that accepts any argument value.
     */
    public static function any(): self
    {
        return new self('any');
    }

    /**
     * Creates a matcher that accepts arguments of the specified type or class.
     *
     * @param string $type the expected type name (e.g. 'string', 'int') or FQCN
     */
    public static function type(string $type): self
    {
        return new self('type', $type);
    }

    /**
     * Creates a matcher that uses a custom callback to validate the argument.
     *
     * @param Closure $callback predicate that receives the actual value and returns true/false
     */
    public static function callback(Closure $callback): self
    {
        return new self('callback', $callback);
    }

    /**
     * Creates a matcher that requires no arguments were passed.
     */
    public static function noArgs(): self
    {
        return new self('noArgs');
    }

    /**
     * Creates a matcher that matches all remaining arguments in a call.
     * Must be the last matcher in the expected args list.
     */
    public static function cetera(): self
    {
        return new self('cetera');
    }

    /**
     * Creates a matcher that checks the argument is an instance of the given class.
     *
     * @param string $class the expected FQCN
     */
    public static function instanceOf(string $class): self
    {
        return new self('instanceOf', $class);
    }

    /**
     * Creates a matcher that checks an array contains all given key/value pairs.
     *
     * @param array $subset the expected key/value pairs
     */
    public static function arrayIncluding(array $subset): self
    {
        return new self('arrayIncluding', $subset);
    }

    /**
     * Creates a matcher using a custom predicate (alias for callback).
     *
     * @param Closure $fn predicate that receives the actual value and returns true/false
     */
    public static function satisfy(Closure $fn): self
    {
        return new self('callback', $fn);
    }

    /**
     * Creates a matcher that checks a string starts with the given prefix.
     *
     * @param string $prefix the expected prefix
     */
    public static function startWith(string $prefix): self
    {
        return new self('startWith', $prefix);
    }

    /**
     * Tests whether the given actual value satisfies this matcher's constraint.
     *
     * @param mixed $actual the value to test against
     */
    public function matches(mixed $actual): bool
    {
        return match ($this->type) {
            'any', 'noArgs', 'cetera' => true,
            'type' => get_debug_type($actual) === $this->value || ($actual instanceof $this->value),
            'callback' => ($this->value)($actual),
            'instanceOf' => $actual instanceof $this->value,
            'arrayIncluding' => is_array($actual) && $this->arrayContainsSubset($actual),
            'startWith' => is_string($actual) && str_starts_with($actual, $this->value),
            default => false,
        };
    }

    /**
     * Returns true if this matcher is the cetera() wildcard.
     */
    public function isCetera(): bool
    {
        return $this->type === 'cetera';
    }

    /**
     * Returns true if this matcher is the noArgs() constraint.
     */
    public function isNoArgs(): bool
    {
        return $this->type === 'noArgs';
    }

    /**
     * Matches an actual argument list against an expected pattern.
     * Handles noArgs (single-element check for empty actual) and
     * cetera (return true when reached, matching all remaining args).
     *
     * @param array $actual the arguments that were actually passed
     * @param array $expected the expected argument pattern (may include matchers)
     */
    public static function matchArgs(array $actual, array $expected): bool
    {
        // noArgs: single-element expected with noArgs matcher
        if (count($expected) === 1 && $expected[0] instanceof self && $expected[0]->isNoArgs()) {
            return count($actual) === 0;
        }

        foreach ($expected as $i => $exp) {
            // cetera: matches all remaining args from this position
            if ($exp instanceof self && $exp->isCetera()) {
                return true;
            }

            // No more actual args but still have expected
            if (!array_key_exists($i, $actual)) {
                return false;
            }

            if ($exp instanceof self) {
                if (!$exp->matches($actual[$i])) {
                    return false;
                }
            } elseif ($actual[$i] !== $exp) {
                return false;
            }
        }

        // If actual has more args than expected (and no cetera), it's a mismatch
        return count($actual) === count($expected);
    }

    /**
     * Checks whether all key-value pairs in this matcher's value exist in the actual array.
     */
    private function arrayContainsSubset(array $actual): bool
    {
        foreach ($this->value as $key => $value) {
            if (!array_key_exists($key, $actual) || $actual[$key] !== $value) {
                return false;
            }
        }
        return true;
    }
}
