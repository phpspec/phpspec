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

use PhpSpec\Mock\ArgumentMatcher;
use PhpSpec\Mock\Double;
use PhpSpec\Mock\Expectation;
use PhpSpec\Mock\LastCallDouble;
use PhpSpec\Mock\MatchableDouble;

/**
 * Creates a test double for the given class or interface.
 *
 * @param class-string<object> $class fully qualified class or interface name
 * @return object the generated test double instance
 * @throws InvalidArgumentException if the class or interface does not exist
 * @throws ReflectionException
 */
function mock(string $class): object
{
    if (!class_exists($class) && !interface_exists($class)) {
        throw new InvalidArgumentException(
            "Cannot create mock: class or interface '$class' does not exist. " .
            'Make sure the class is autoloaded or the file is included.',
        );
    }
    return Double::getInstance($class);
}

/**
 * Creates an argument matcher that accepts any value.
 */
function any(): ArgumentMatcher
{
    return ArgumentMatcher::any();
}

/**
 * Creates an argument matcher that checks for a specific type or class.
 *
 * @param string $type the expected type name or FQCN
 */
function type(string $type): ArgumentMatcher
{
    return ArgumentMatcher::type($type);
}

/**
 * Creates an argument matcher that validates using a custom callback.
 *
 * @param Closure $fn predicate receiving the actual value, returns true if it matches
 */
function callback(Closure $fn): ArgumentMatcher
{
    return ArgumentMatcher::callback($fn);
}

/**
 * Creates an argument matcher that requires no arguments were passed.
 */
function noArgs(): ArgumentMatcher
{
    return ArgumentMatcher::noArgs();
}

/**
 * Creates an argument matcher that matches all remaining arguments.
 */
function cetera(): ArgumentMatcher
{
    return ArgumentMatcher::cetera();
}

/**
 * Creates an argument matcher that checks the argument is an instance of the given class.
 *
 * @param string $class the expected FQCN
 */
function anInstanceOf(string $class): ArgumentMatcher
{
    return ArgumentMatcher::instanceOf($class);
}

/**
 * Creates an argument matcher that checks an array contains all given key/value pairs.
 *
 * @param array $subset the expected key/value pairs
 */
function arrayIncluding(array $subset): ArgumentMatcher
{
    return ArgumentMatcher::arrayIncluding($subset);
}

/**
 * Creates an argument matcher using a custom predicate (alias for callback).
 *
 * @param Closure $fn predicate receiving the actual value, returns true if it matches
 */
function satisfy(Closure $fn): ArgumentMatcher
{
    return ArgumentMatcher::satisfy($fn);
}

/**
 * Creates an argument matcher that checks a string starts with the given prefix.
 *
 * @param string $prefix the expected prefix
 */
function startWith(string $prefix): ArgumentMatcher
{
    return ArgumentMatcher::startWith($prefix);
}

/**
 * Captures the most recent mock method call for stub configuration.
 * Must be called immediately after a mock method invocation.
 * Usage: allow($mock->method())->toReturn(value)
 *
 * When the mock method returns an object type, the argument is a MatchableDouble
 * wrapper and is used directly. For scalar returns, falls back to the static
 * $lastCallForAllow side-channel.
 *
 * @param mixed $returnValue the mock method's return value
 * @return LastCallDouble stub configurator for the tracked method
 * @throws LogicException if no preceding mock method call was tracked
 */
function allow(mixed $returnValue = null): LastCallDouble
{
    Expectation::$lastDouble = null;
    Expectation::$lastMockReturn = null;

    if ($returnValue instanceof MatchableDouble) {
        Expectation::$lastCallForAllow = null;
        $double = $returnValue->______PhpSpecGetDouble();
        $method = $returnValue->______PhpSpecGetMethod();
        $stack = $double->______PhpSpecGetStubbedCalls();
        $top = $stack->peek();
        $args = ($top && $top->method === $method && !empty($top->arguments)) ? $top->arguments : null;
        return new LastCallDouble($double, $method, $args);
    }

    $lastDouble = Expectation::$lastCallForAllow;
    Expectation::$lastCallForAllow = null;

    if ($lastDouble === null) {
        throw new LogicException(
            'allow() must be called immediately after a mock method call. '
            . 'Example: allow($mock->method())->toReturn(value)',
        );
    }

    // Extract args from the stack for arg-based stub dispatch
    $double = $lastDouble->______PhpSpecGetDouble();
    $method = $lastDouble->______PhpSpecGetMethod();
    $stack = $double->______PhpSpecGetStubbedCalls();
    $top = $stack->peek();
    $args = ($top && $top->method === $method && !empty($top->arguments)) ? $top->arguments : null;

    return new LastCallDouble($double, $method, $args);
}
