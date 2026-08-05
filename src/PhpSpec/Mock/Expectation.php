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
 * verification matchers (toBeCalled, toBeCalledWith, toBeCalledTimes) and
 * stub configuration (toReturn, toThrow).
 */
final class Expectation extends BaseExpectation
{
    /** @var array<string, MockedMethod> Maps double class names to their most recent MockedMethod */
    public static array $registry = [];

    /** @var LastCallDouble|null Tracks the most recent mock method call for scalar-return bridging */
    public static ?LastCallDouble $lastDouble = null;

    /** @var mixed Caches the return value of the last mock method call */
    public static mixed $lastMockReturn = null;

    /** @var LastCallDouble|null Preserved across stub checks so allow() can reconfigure stubs */
    public static ?LastCallDouble $lastCallForAllow = null;

    /** @var MatchableDouble The mock subject being verified */
    private MatchableDouble $mockSubject;

    /**
     * Creates a mock expectation wrapping a MatchableDouble subject.
     *
     * @param MatchableDouble $subject the mock wrapper returned by a doubled method call
     * @param string $file the spec file where the expectation was created
     * @param int $line the line number in the spec file
     */
    public function __construct(MatchableDouble $subject, string $file, int $line)
    {
        $this->mockSubject = $subject;
        parent::__construct($subject, $file, $line);
    }

    /**
     * Configures the mock method to return a specific value when called.
     *
     * @param mixed $value the value the stubbed method should return
     * @return $this
     */
    public function toReturn(mixed $value): static
    {
        $this->mockSubject->toReturn($value);
        return $this;
    }

    /**
     * Configures the mock method to throw an exception when called.
     *
     * @param string $exceptionClass the exception class to throw
     * @param string|null $message the exception message
     * @return $this
     */
    public function toThrow(string $exceptionClass = '', ?string $message = null): static
    {
        $this->mockSubject->toThrow($exceptionClass, $message ?? '');
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
            null, // toBeCalled() counts all calls regardless of args
            $this->mockSubject,
        );
    }

    /**
     * Alias for toBeCalled() for past-tense readability.
     */
    public function toHaveBeenCalled(): CallCountExpectation
    {
        return $this->toBeCalled();
    }

    /**
     * Asserts that the mock method was called with the specified arguments.
     * Supports ArgumentMatcher instances for flexible matching.
     *
     * @param mixed $expectedArgs the expected arguments, may include ArgumentMatcher instances
     * @return $this
     */
    public function toBeCalledWith(...$expectedArgs): static
    {
        $double = $this->getGeneratedDouble();
        $class = $double->______PhpSpecNameOfClassDoubled();
        $calls = $double->______PhpSpecGetStubbedCalls();
        $method = $calls->peek();
        if ($method === null) {
            throw new \LogicException('No method call recorded on the mock. Call a method before using toBeCalledWith().');
        }
        $methodName = $method->method;
        $method->unCall();
        DispatcherRegistry::dispatcher()->dispatch(new MethodMocked($double, $method, 1), MethodMocked::NAME);

        return $this->should(
            get_class($double),
            fn($mocked) => $mocked->wasCalled() && $this->matchArguments($mocked->arguments, $expectedArgs),
            'Expected %s to be called with specific arguments',
            $class . '::' . $methodName . '()',
        );
    }

    /**
     * Asserts that the mock method was called exactly the specified number of times.
     *
     * @param int $times the expected call count
     * @return $this
     */
    public function toBeCalledTimes(int $times): static
    {
        $double = $this->getGeneratedDouble();
        $class = $double->______PhpSpecNameOfClassDoubled();
        $calls = $double->______PhpSpecGetStubbedCalls();
        $method = $calls->peek();
        if ($method === null) {
            throw new \LogicException('No method call recorded on the mock. Call a method before using toBeCalledTimes().');
        }
        $methodName = $method->method;
        $method->unCall();
        DispatcherRegistry::dispatcher()->dispatch(new MethodMocked($double, $method, $times), MethodMocked::NAME);

        $trace = debug_backtrace()[0];
        $file = $trace['file'] ?? 'unknown';
        $line = $trace['line'] ?? 0;

        return $this->expectation(function () use ($double, $methodName, $times, $class, $file, $line) {
            $stack = $double->______PhpSpecGetStubbedCalls();
            $actual = $stack->countCallsTo($methodName);

            return match (true) {
                $actual === $times => MatchResult::passed(),
                default => MatchResult::failed(
                    $this->mockSubject,
                    $times,
                    "Expected $class::$methodName() to be called $times time(s), but was called $actual time(s)",
                    $file,
                    $line,
                )
            };
        });
    }

    /**
     * Compares actual call arguments against expected values.
     * Uses ArgumentMatcher::matches() for matcher instances, strict equality otherwise.
     *
     * @param array<int, mixed> $actual the arguments that were actually passed
     * @param array<int|string, mixed> $expected the expected arguments to compare against
     */
    private function matchArguments(array $actual, array $expected): bool
    {
        return ArgumentMatcher::matchArgs($actual, $expected);
    }

    /**
     * Dispatches a mock verification as an expectation event.
     * Handles negation by inverting the match closure and adjusting the message.
     *
     * @param mixed $doubleName the double class name used as registry key
     * @param Closure $match closure that receives the MockedMethod and returns pass/fail
     * @param mixed $message the failure message template with %s placeholders
     * @param mixed $values values to interpolate into the message
     * @return $this
     */
    private function should($doubleName, Closure $match, $message, ...$values): static
    {
        $trace = debug_backtrace()[1];
        $file = $trace['file'] ?? 'unknown';
        $line = $trace['line'] ?? 0;

        if ($this->negated) {
            $originalMatch = $match;
            $match = fn($mocked) => !$originalMatch($mocked);
            $message = str_replace(' to ', ' not to ', $message);
        }

        return $this->expectation(function () use ($doubleName, $match, $values, $message, $file, $line) {

            $mocked = self::$registry[$doubleName];
            $result = $match($mocked);

            return match (true) {
                $result => MatchResult::passed(),
                default => MatchResult::failed(
                    $this->mockSubject,
                    $values[0] ?? null,
                    self::formatMessage($message, ...$values),
                    $file,
                    $line,
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
