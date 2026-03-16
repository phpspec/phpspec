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

use PhpSpec\EventDispatcher\DispatcherRegistry;
use PhpSpec\EventDispatcher\Event\ContextCreated;
use PhpSpec\EventDispatcher\Event\ExampleCreated;
use PhpSpec\Mock\MatchableDouble;
use PhpSpec\Specification\Context;
use PhpSpec\Specification\Example;
use PhpSpec\Specification\Expectation;
use PhpSpec\Specification\PendingException;
use PhpSpec\Specification\SkippedException;

/**
 * Registers a new example (test case) in the current context scope.
 *
 * @param string $title descriptive label for the example
 * @param Closure $example executable test body
 */
function it(string $title, Closure $example): void
{
    $d = DispatcherRegistry::get();
    $it = new Example($title, $example, $d);

    $scope = $d->currentScope();
    if ($scope) {
        $scope->addSpecBlock($it);
    }

    $d->dispatch(
        new ExampleCreated($title, $it),
        ExampleCreated::NAME,
    );
}

/**
 * Alias for it(). Registers a new example in the current context scope.
 *
 * @param string $title descriptive label for the example
 * @param Closure $example executable test body
 */
function its(string $title, Closure $example): void
{
    it($title, $example);
}

/**
 * Creates an Expectation for the given subject value.
 *
 * Detects mock doubles and returns a Mock\Expectation for stub/verify assertions.
 * Falls back to a standard Expectation for plain values.
 *
 * @param mixed $subject value or mock return to assert against
 * @return Expectation
 */
function expect(mixed $subject): Expectation
{
    $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
    $file = $trace['file'];
    $line = $trace['line'];

    if ($subject instanceof MatchableDouble) {
        \PhpSpec\Mock\Expectation::$lastDouble = null;
        \PhpSpec\Mock\Expectation::$lastMockReturn = null;
        \PhpSpec\Mock\Expectation::$lastCallForAllow = null;
        return new \PhpSpec\Mock\Expectation($subject, $file, $line);
    }

    if (\PhpSpec\Mock\Expectation::$lastDouble !== null) {
        $lastDouble = \PhpSpec\Mock\Expectation::$lastDouble;
        $lastReturn = \PhpSpec\Mock\Expectation::$lastMockReturn;
        \PhpSpec\Mock\Expectation::$lastDouble = null;
        \PhpSpec\Mock\Expectation::$lastMockReturn = null;
        \PhpSpec\Mock\Expectation::$lastCallForAllow = null;

        if ($subject === $lastReturn) {
            return new \PhpSpec\Mock\Expectation($lastDouble, $file, $line);
        }
    }

    return new Expectation($subject, $file, $line);
}

/**
 * Declares a describe block (context) grouping related examples.
 *
 * @param string $context description or class name for the group
 * @param Closure $examples closure containing nested it()/describe()/let() calls
 */
function describe(string $context, Closure $examples): void
{
    $d = DispatcherRegistry::get();
    $describe = new Context($context, $examples, $d);

    $scope = $d->currentScope();
    if ($scope) {
        $scope->addSpecBlock($describe);
    }

    $d->dispatch(new ContextCreated($context, $describe), ContextCreated::NAME);
}

/**
 * Alias for describe(). Declares a context block grouping related examples.
 *
 * @param string $context description for the group
 * @param Closure $examples closure containing nested spec DSL calls
 */
function context(string $context, Closure $examples): void
{
    describe($context, $examples);
}

/**
 * Defines shared state on the current context's world (Subject).
 *
 * Accepts either a property name + setter closure, or a single closure whose
 * type-hinted parameters are auto-resolved as mock doubles.
 *
 * @param string|Closure $propertyOrSetter property name or injection closure
 * @param Closure|null $setter value factory when first arg is a property name
 */
function let(string|Closure $propertyOrSetter, ?Closure $setter = null): void
{
    $scope = DispatcherRegistry::get()->currentScope();
    if ($scope instanceof Context) {
        if ($propertyOrSetter instanceof Closure) {
            $scope->modifyWithInjection($propertyOrSetter);
        } else {
            $scope->modify($propertyOrSetter, $setter);
        }
    }
}

/**
 * Registers a hook to run once before all examples in the current context.
 *
 * @param Closure $hook setup callback
 */
function beforeAll(Closure $hook): void
{
    $scope = DispatcherRegistry::get()->currentScope();
    if ($scope instanceof Context) {
        $scope->addBeforeAll($hook);
    }
}

/**
 * Registers a hook to run once after all examples in the current context.
 *
 * @param Closure $hook teardown callback
 */
function afterAll(Closure $hook): void
{
    $scope = DispatcherRegistry::get()->currentScope();
    if ($scope instanceof Context) {
        $scope->addAfterAll($hook);
    }
}

/**
 * Registers a hook to run before each example in the current context.
 *
 * @param Closure $hook per-example setup callback
 */
function beforeEach(Closure $hook): void
{
    $scope = DispatcherRegistry::get()->currentScope();
    if ($scope instanceof Context) {
        $scope->addBeforeEach($hook);
    }
}

/**
 * Registers a hook to run after each example in the current context.
 *
 * @param Closure $hook per-example teardown callback
 */
function afterEach(Closure $hook): void
{
    $scope = DispatcherRegistry::get()->currentScope();
    if ($scope instanceof Context) {
        $scope->addAfterEach($hook);
    }
}

/**
 * Registers a pending (skipped) example. The example body is not executed.
 *
 * @param string $title descriptive label for the example
 * @param Closure $example test body (will not run)
 */
function xit(string $title, Closure $example): void
{
    $d = DispatcherRegistry::get();
    $it = new Example($title, $example, $d);
    $it->setPending(true);

    $scope = $d->currentScope();
    $scope?->addSpecBlock($it);

    $d->dispatch(
        new ExampleCreated($title, $it),
        ExampleCreated::NAME,
    );
}

/**
 * Declares a pending describe block. All nested examples are skipped.
 *
 * @param string $context description for the group
 * @param Closure $examples closure containing nested spec DSL calls
 */
function xdescribe(string $context, Closure $examples): void
{
    $d = DispatcherRegistry::get();
    $describe = new Context($context, $examples, $d);
    $describe->setPending(true);

    $scope = $d->currentScope();
    $scope?->addSpecBlock($describe);

    $d->dispatch(new ContextCreated($context, $describe), ContextCreated::NAME);
}

/**
 * Alias for xdescribe(). Declares a pending context block.
 *
 * @param string $context description for the group
 * @param Closure $examples closure containing nested spec DSL calls
 */
function xcontext(string $context, Closure $examples): void
{
    xdescribe($context, $examples);
}

/**
 * Marks the current example as pending by throwing a PendingException.
 *
 * @param string $message reason the example is pending
 * @throws PendingException always
 */
function pending(string $message = 'Not yet implemented'): never
{
    throw new PendingException($message);
}

/**
 * Marks the current example as skipped by throwing a SkippedException.
 *
 * @param string $message reason the example is skipped
 * @throws SkippedException always
 */
function skip(string $message = 'Skipped'): never
{
    throw new SkippedException($message);
}

/**
 * Registers a custom matcher available to all expect() assertions.
 *
 * @param string $name matcher name (e.g. 'toBePositive')
 * @param Closure $matcher callback receiving (subject, ...args) returning bool
 * @param string $message failure message template with %s placeholders
 */
function addMatcher(string $name, Closure $matcher, string $message): void
{
    Expectation::addMatcher($name, $matcher, $message);
}

/**
 * Registers a focused example. Only focused examples run when any exist in a context.
 *
 * @param string $title descriptive label for the example
 * @param Closure $example executable test body
 */
function fit(string $title, Closure $example): void
{
    $d = DispatcherRegistry::get();
    $it = new Example($title, $example, $d);
    $it->setFocused(true);

    $scope = $d->currentScope();
    $scope?->addSpecBlock($it);

    $d->dispatch(
        new ExampleCreated($title, $it),
        ExampleCreated::NAME,
    );
}

/**
 * Declares a focused describe block. Non-focused siblings are skipped.
 *
 * @param string $context description for the group
 * @param Closure $examples closure containing nested spec DSL calls
 */
function fdescribe(string $context, Closure $examples): void
{
    $d = DispatcherRegistry::get();
    $describe = new Context($context, $examples, $d);
    $describe->setFocused(true);

    $scope = $d->currentScope();
    $scope?->addSpecBlock($describe);

    $d->dispatch(new ContextCreated($context, $describe), ContextCreated::NAME);
}

/**
 * Alias for fdescribe(). Declares a focused context block.
 *
 * @param string $context description for the group
 * @param Closure $examples closure containing nested spec DSL calls
 */
function fcontext(string $context, Closure $examples): void
{
    fdescribe($context, $examples);
}
