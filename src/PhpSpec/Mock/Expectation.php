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
use PhpSpec\EventDispatcher\DispatcherRegistry;
use PhpSpec\EventDispatcher\Event\ExpectationStarted;
use PhpSpec\EventDispatcher\Event\MatchCreated;
use PhpSpec\EventDispatcher\Event\Mock\MethodMocked;
use PhpSpec\Result\MatchResult;
use PhpSpec\Specification\Expectation as BaseExpectation;

/**
 * Mock-specific expectation that extends the standard Expectation with call
 * verification matchers (toBeCalled, toHaveBeenCalled) and
 * stub configuration (toReturn, toThrow).
 */
final class Expectation extends BaseExpectation
{
    /** @var LastCallDouble|null Tracks the most recent mock method call for scalar-return bridging */
    public static ?LastCallDouble $lastDouble = null;

    /** @var mixed Caches the return value of the last mock method call */
    public static mixed $lastMockReturn = null;

    /** @var LastCallDouble|null Preserved across stub checks so allow() can reconfigure stubs */
    public static ?LastCallDouble $lastCallForAllow = null;

    /** @var \Throwable|null The exception a stub withheld from a call the spec made itself, for toThrow() to see */
    public static ?\Throwable $withheld = null;

    /** @var MatchableDouble The mock subject being verified */
    private MatchableDouble $mockSubject;

    /** @var MatchableDouble The call a stub is set on, held to the arguments it was written with */
    private MatchableDouble $stubbed;

    /**
     * Creates a mock expectation on a doubled method call: the call matchers
     * (toBeCalled, toHaveBeenCalled) judge the double, the ordinary matchers
     * judge what the call returned, so a stubbed call compares its value and
     * verifies itself alike.
     *
     * @param MatchableDouble $double the mock wrapper the doubled method call stands for
     * @param string $file the spec file where the expectation was created
     * @param int $line the line number in the spec file
     * @param mixed ...$returned what the call returned, when a stub gave it a value; else the double itself
     */
    public function __construct(MatchableDouble $double, string $file, int $line, mixed ...$returned)
    {
        $this->mockSubject = $double;
        parent::__construct($returned === [] ? $double : $returned[0], $file, $line);
        $this->stubbed = $this->declared();

    }

    /**
     * Configures the mock method to return a specific value when called.
     *
     * @param mixed $value the value the stubbed method should return
     * @return $this
     */
    public function toReturn(mixed $value): static
    {
        $this->stubbed->toReturn($value);
        return $this;
    }

    /**
     * Configures the mock method to throw an exception when called: the very
     * instance given, or one built from a class name and a message.
     *
     * @param string|\Throwable $exception the exception to throw: a class name, or the very instance
     * @param string|null $message the exception message, when a class is given
     * @return $this
     */
    public function toThrow(string|\Throwable $exception = '', ?string $message = null): static
    {
        $this->stubbed->toThrow($exception, $message ?? '');
        return $this;
    }

    /**
     * Asserts that the mock method was called at least once.
     * Returns a CallCountExpectation for fluent count chaining
     * (once(), twice(), exactly(N)->times(), etc.).
     */
    public function toBeCalled(): CallCountExpectation
    {
        $double = $this->getGeneratedDouble();
        $class = $double->______PhpSpecNameOfClassDoubled();
        $calls = $double->______PhpSpecGetStubbedCalls();
        $method = $calls->peek();
        if ($method === null) {
            throw new \LogicException('No method call recorded on the mock. Call a method before using toBeCalled().');
        }
        $methodName = $method->method;
        $method->unCall();
        DispatcherRegistry::dispatcher()->dispatch(new MethodMocked($double, $method, 1), MethodMocked::NAME);

        $trace = debug_backtrace()[0];

        return new CallCountExpectation(
            $methodName,
            $calls,
            $class,
            $trace['file'] ?? 'unknown',
            $trace['line'] ?? 0,
            $this->negated,
            $method->arguments,
        );
    }

    /**
     * Judges now, from the calls recorded so far, that the call expect() was
     * handed has been made: the past tense, for after the act. The arguments
     * it was written with are part of the question. toBeCalled() is the
     * future tense, declared before the act and judged when the example ends.
     */
    public function toHaveBeenCalled(): static
    {
        $double = $this->getGeneratedDouble();
        $class = $double->______PhpSpecNameOfClassDoubled();
        $calls = $double->______PhpSpecGetStubbedCalls();
        $method = $calls->peek();

        if ($method === null) {
            throw new \LogicException('No method call recorded on the mock. Call a method before using toHaveBeenCalled().');
        }

        $methodName = $method->method;
        $method->unCall();
        $argPattern = $method->arguments;
        DispatcherRegistry::dispatcher()->dispatch(new MethodMocked($double, $method, 1), MethodMocked::NAME);

        return $this->should(
            fn(): bool => $calls->countCallsToWithArgs($methodName, $argPattern) >= 1,
            "Expected $class::$methodName() to have been called",
            new CallComparison($class, $methodName, $argPattern, 'at least 1', $calls),
            'toHaveBeenCalled',
        );
    }



    /**
     * Dispatches a mock verification as an expectation event, judged at the
     * end of the example over every call the double received by then.
     * Handles negation by inverting the match closure and adjusting the message.
     *
     * @param Closure(): bool $match whether the calls received satisfy the expectation
     * @param string $message the failure message
     * @param CallComparison $comparison the call wanted and the calls received, for the failure's data
     * @param string $matcher the matcher's name
     * @return $this
     */
    private function should(Closure $match, string $message, CallComparison $comparison, string $matcher): static
    {
        $trace = debug_backtrace()[1];
        $file = $trace['file'] ?? 'unknown';
        $line = $trace['line'] ?? 0;

        if ($this->negated) {
            $originalMatch = $match;
            $match = fn(): bool => !$originalMatch();
            $message = str_replace(' to ', ' not to ', $message);
        }

        return $this->expectation(function () use ($match, $message, $comparison, $matcher, $file, $line) {
            return match (true) {
                $match() => MatchResult::passed(),
                default => MatchResult::failed(
                    $comparison->received(),
                    $comparison->wanted(),
                    $message,
                    $file,
                    $line,
                    matcher: $matcher,
                    negated: $this->negated,
                )
            };
        });
    }

    /**
     * Dispatches ExpectationStarted and MatchCreated events for a match closure.
     *
     * @param mixed $match closure that returns a MatchResult
     * @return $this
     */
    private function expectation($match): static
    {
        DispatcherRegistry::dispatcher()->dispatch(new ExpectationStarted(), ExpectationStarted::NAME);
        DispatcherRegistry::dispatcher()->dispatch(new MatchCreated($match), MatchCreated::NAME);
        return $this;
    }

    /**
     * The call expect() was handed is expected: made from the code under
     * spec, it is no longer a call nothing declared. Returned held to the
     * arguments it was written with, for a stub to answer that call alone.
     */
    private function declared(): MatchableDouble
    {
        $double = $this->mockSubject->______PhpSpecGetDouble();

        if (!$double instanceof GeneratedDouble) {
            return $this->mockSubject;
        }

        $method = $this->mockSubject->______PhpSpecGetMethod();
        $args = $double->______PhpSpecGetStubbedCalls()->writtenFor($method);
        $double->______PhpSpecDeclare($method, $args);

        return new LastCallDouble($double, $method, $args);
    }

    /**
     * Retrieves the GeneratedDouble from the mock subject.
     */
    private function getGeneratedDouble(): GeneratedDouble
    {
        $double = $this->mockSubject->______PhpSpecGetDouble();
        if (!$double instanceof GeneratedDouble) {
            throw new \LogicException('Expected a GeneratedDouble instance from ______PhpSpecGetDouble()');
        }
        return $double;
    }

}
