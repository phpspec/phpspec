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

namespace PhpSpec\Specification;

use BadMethodCallException;
use Closure;
use PhpSpec\Browser\Response;

/**
 * Fluent assertion API returned by expect(). Provides built-in matchers (toBe, toContain, etc.)
 * and supports custom matchers via addMatcher(). Negation is handled by the not() method.
 */
class Expectation
{
    /** @var bool whether the next matcher should invert its result */
    protected bool $negated = false;

    /** @var array<string, array{matcher: Closure, message: string|Closure}> registered custom matchers */
    private static array $customMatchers = [];

    /**
     * Registers a custom matcher globally available to all expectations.
     *
     * @param string $name matcher name (e.g. 'toBePositive')
     * @param Closure $matcher callback receiving (subject, ...args) returning bool
     * @param string|Closure $message failure message template with %s placeholders, or a closure(subject, ...args) returning string
     * @return void
     */
    public static function addMatcher(string $name, Closure $matcher, string|Closure $message): void
    {
        self::$customMatchers[$name] = ['matcher' => $matcher, 'message' => $message];
    }

    /**
     * Dispatches custom matchers or throws BadMethodCallException with typo suggestions.
     *
     * @param string $name matcher name
     * @param array<mixed> $arguments matcher arguments
     * @return static
     * @throws BadMethodCallException if no matcher matches the name
     */
    public function __call(string $name, array $arguments): static
    {
        if (isset(self::$customMatchers[$name])) {
            $custom = self::$customMatchers[$name];
            $message = $custom['message'];
            if ($message instanceof Closure) {
                $message = $message($this->subject, ...$arguments);
            }
            return $this->match(
                fn($expected) => ($custom['matcher'])($expected, ...$arguments),
                $message,
                $this->subject,
                ...$arguments,
            );
        }

        // Predicate matchers: toBe<X> → is<X>(), toHave<X> → has<X>()
        if (is_object($this->subject)) {
            $method = null;
            $prefix = null;
            if (str_starts_with($name, 'toBe') && strlen($name) > 4 && ctype_upper($name[4])) {
                $method = 'is' . substr($name, 4);
                $prefix = 'be';
            } elseif (str_starts_with($name, 'toHave') && strlen($name) > 6 && ctype_upper($name[6])) {
                $method = 'has' . substr($name, 6);
                $prefix = 'have';
            }
            if ($method !== null && method_exists($this->subject, $method)) {
                $humanized = strtolower(ltrim((string) preg_replace('/[A-Z]/', ' $0', substr($name, $prefix === 'be' ? 4 : 6))));
                return $this->match(
                    fn($expected) => (bool) $expected->$method(...$arguments),
                    "Expected %s to {$prefix} {$humanized}",
                    $this->subject,
                );
            }
        }

        $available = array_merge(
            ['toBe', 'toBeLike', 'toHaveCount', 'toBeAnInstanceOf', 'toBeTrue', 'toBeFalse',
                'toBeNull', 'toBeEmpty', 'toContain', 'toMatch', 'toBeGreaterThan', 'toBeLessThan',
                'toHaveKey', 'toStartWith', 'toEndWith', 'toHaveProperty', 'toBeCallable',
                'toBeOfType', 'toBeGreaterThanOrEqualTo', 'toBeLessThanOrEqualTo', 'toBeBetween',
                'toBeTruthy', 'toBeFalsy', 'toBeCloseTo', 'toHaveLength', 'toContainEqual',
                'toRespondTo', 'toSatisfy', 'toBeOk', 'toBeBad', 'toHaveStatus', 'toHavePath',
                'toHaveHeader', 'toRedirectTo', 'toThrow'],
            array_keys(self::$customMatchers),
        );
        $suggestions = array_filter($available, fn($m) => levenshtein($name, $m) <= 3);
        $hint = !empty($suggestions) ? ' Did you mean: ' . implode(', ', $suggestions) . '?' : '';
        throw new BadMethodCallException("Unknown matcher: {$name}.{$hint}");
    }

    /**
     * @param mixed $subject value being asserted against
     * @param string $file source file where expect() was called
     * @param int $line source line where expect() was called
     * @param EventfulExpectation|null $eventCreator bridge to the event system
     */
    public function __construct(
        private readonly mixed $subject,
        string $file,
        int $line,
        private ?EventfulExpectation $eventCreator = null,
    ) {
        $this->eventCreator = $eventCreator ?? new EventfulExpectation($subject, $file, $line);
    }

    /**
     * Returns a negated clone of this expectation. The next matcher will invert its result.
     *
     * @return static negated expectation
     */
    public function not(): static
    {
        $negated = clone $this;
        $negated->negated = true;
        return $negated;
    }

    /**
     * Asserts strict identity (===) between subject and actual value.
     *
     * @param mixed $actual expected value
     * @return static
     */
    public function toBe(mixed $actual): static
    {
        return $this->match(
            fn($expected) => $actual === $expected,
            'Expected %s to be %s',
            $this->subject,
            $actual,
            ['__fake' => self::exportValue($actual)],
        );
    }

    /**
     * Asserts loose equality (==) between subject and actual value.
     *
     * @param mixed $actual expected value
     * @return static
     */
    public function toBeLike(mixed $actual): static
    {
        return $this->match(
            fn($expected) => $actual == $expected,
            'Expected %s to be %s',
            $this->subject,
            $actual,
            ['__fake' => self::exportValue($actual)],
        );
    }

    /**
     * Asserts that the subject (array/countable) has the given count.
     *
     * @param int $actual expected element count
     * @return static
     */
    public function toHaveCount(mixed $actual): static
    {
        return $this->match(
            fn($expected) => $actual === count($expected),
            'Expected %s to be %s',
            $this->subject,
            $actual,
            ['__fake' => 'array_fill(0, ' . (int) $actual . ', null)'],
        );
    }

    /**
     * Asserts that the subject is an instance of the given class or interface.
     *
     * @param string $actual fully qualified class name
     * @return static
     */
    public function toBeAnInstanceOf(string $actual): static
    {
        return $this->match(
            fn($expected) => $expected instanceof $actual,
            'Expected %s to be an instance of %s',
            $this->subject,
            $actual,
            ['__fake' => 'new \\' . ltrim($actual, '\\') . '()'],
        );
    }

    /**
     * Asserts that the subject is strictly true.
     *
     * @return static
     */
    public function toBeTrue(): static
    {
        return $this->match(
            fn($expected) => $expected === true,
            'Expected %s to be true',
            $this->subject,
            ['__fake' => 'true'],
        );
    }

    /**
     * Asserts that the subject is strictly false.
     *
     * @return static
     */
    public function toBeFalse(): static
    {
        return $this->match(
            fn($expected) => $expected === false,
            'Expected %s to be false',
            $this->subject,
            ['__fake' => 'false'],
        );
    }

    /**
     * Asserts that the subject is null.
     *
     * @return static
     */
    public function toBeNull(): static
    {
        return $this->match(
            fn($expected) => $expected === null,
            'Expected %s to be null',
            $this->subject,
            ['__fake' => 'null'],
        );
    }

    /**
     * Asserts that the subject is empty (empty string, empty array, null, etc.).
     *
     * @return static
     */
    public function toBeEmpty(): static
    {
        return $this->match(
            fn($expected) => empty($expected),
            'Expected %s to be empty',
            $this->subject,
            ['__fake' => '[]'],
        );
    }

    /**
     * Asserts that the subject contains the given value (in_array for arrays, str_contains for strings).
     *
     * @param mixed $value element or substring to find
     * @return static
     */
    public function toContain(mixed $value): static
    {
        $fake = is_string($value) ? self::exportValue($value) : '[' . self::exportValue($value) . ']';
        return $this->match(
            fn($expected) => is_array($expected) ? in_array($value, $expected, true) : str_contains($expected, $value),
            'Expected %s to contain %s',
            $this->subject,
            $value,
            ['__fake' => $fake],
        );
    }

    /**
     * Asserts that the subject string matches the given regular expression.
     *
     * @param string $pattern PCRE pattern
     * @return static
     */
    public function toMatch(string $pattern): static
    {
        return $this->match(
            fn($expected) => preg_match($pattern, $expected) === 1,
            'Expected %s to match %s',
            $this->subject,
            $pattern,
        );
    }

    /**
     * Asserts that the subject is greater than the given value.
     *
     * @param mixed $value comparison threshold
     * @return static
     */
    public function toBeGreaterThan(mixed $value): static
    {
        return $this->match(
            fn($expected) => $expected > $value,
            'Expected %s to be greater than %s',
            $this->subject,
            $value,
            ['__fake' => self::exportValue(is_numeric($value) ? $value + 1 : $value)],
        );
    }

    /**
     * Asserts that the subject is less than the given value.
     *
     * @param mixed $value comparison threshold
     * @return static
     */
    public function toBeLessThan(mixed $value): static
    {
        return $this->match(
            fn($expected) => $expected < $value,
            'Expected %s to be less than %s',
            $this->subject,
            $value,
            ['__fake' => self::exportValue(is_numeric($value) ? $value - 1 : $value)],
        );
    }

    /**
     * Asserts that the subject array has the given key.
     *
     * @param string $key array key to check
     * @return static
     */
    public function toHaveKey(string $key): static
    {
        return $this->match(
            fn($expected) => is_array($expected) && array_key_exists($key, $expected),
            'Expected %s to have key %s',
            $this->subject,
            $key,
            ['__fake' => '[' . self::exportValue($key) . ' => null]'],
        );
    }

    /**
     * Asserts that the subject string starts with the given prefix.
     *
     * @param string $prefix expected beginning of the string
     * @return static
     */
    public function toStartWith(string $prefix): static
    {
        return $this->match(
            fn($expected) => str_starts_with($expected, $prefix),
            'Expected %s to start with %s',
            $this->subject,
            $prefix,
            ['__fake' => self::exportValue($prefix)],
        );
    }

    /**
     * Asserts that the subject string ends with the given suffix.
     *
     * @param string $suffix expected ending of the string
     * @return static
     */
    public function toEndWith(string $suffix): static
    {
        return $this->match(
            fn($expected) => str_ends_with($expected, $suffix),
            'Expected %s to end with %s',
            $this->subject,
            $suffix,
            ['__fake' => self::exportValue($suffix)],
        );
    }

    /**
     * Asserts that the subject object has the given property.
     *
     * @param string $property property name
     * @return static
     */
    public function toHaveProperty(string $property): static
    {
        return $this->match(
            fn($expected) => property_exists($expected, $property),
            'Expected %s to have property %s',
            $this->subject,
            $property,
        );
    }

    /**
     * Asserts that the subject is callable.
     *
     * @return static
     */
    public function toBeCallable(): static
    {
        return $this->match(
            fn($expected) => is_callable($expected),
            'Expected %s to be callable',
            $this->subject,
            ['__fake' => 'function () {}'],
        );
    }

    /**
     * Asserts that the subject matches the given type (via get_debug_type or instanceof).
     *
     * @param string $type type name (e.g. 'string', 'int', or a class name)
     * @return static
     */
    public function toBeOfType(string $type): static
    {
        $fakeMap = ['string' => "''", 'int' => '0', 'float' => '0.0', 'bool' => 'false', 'array' => '[]', 'null' => 'null'];
        $fake = $fakeMap[$type] ?? "new \\{$type}()";
        return $this->match(
            fn($expected) => get_debug_type($expected) === $type || ($expected instanceof $type),
            'Expected %s to be of type %s',
            $this->subject,
            $type,
            ['__fake' => $fake],
        );
    }

    /**
     * Asserts that the subject is greater than or equal to the given value.
     *
     * @param mixed $value comparison threshold
     * @return static
     */
    public function toBeGreaterThanOrEqualTo(mixed $value): static
    {
        return $this->match(
            fn($expected) => $expected >= $value,
            'Expected %s to be greater than or equal to %s',
            $this->subject,
            $value,
            ['__fake' => self::exportValue($value)],
        );
    }

    /**
     * Asserts that the subject is less than or equal to the given value.
     *
     * @param mixed $value comparison threshold
     * @return static
     */
    public function toBeLessThanOrEqualTo(mixed $value): static
    {
        return $this->match(
            fn($expected) => $expected <= $value,
            'Expected %s to be less than or equal to %s',
            $this->subject,
            $value,
            ['__fake' => self::exportValue($value)],
        );
    }

    /**
     * Asserts that the subject is between min and max (inclusive).
     *
     * @param mixed $min lower bound
     * @param mixed $max upper bound
     * @return static
     */
    public function toBeBetween(mixed $min, mixed $max): static
    {
        return $this->match(
            fn($expected) => $expected >= $min && $expected <= $max,
            'Expected %s to be between %s and %s',
            $this->subject,
            $min,
            $max,
            ['__fake' => self::exportValue($min)],
        );
    }

    /**
     * Asserts that the subject is truthy (loosely true).
     *
     * @return static
     */
    public function toBeTruthy(): static
    {
        return $this->match(
            fn($expected) => (bool) $expected === true,
            'Expected %s to be truthy',
            $this->subject,
            ['__fake' => 'true'],
        );
    }

    /**
     * Asserts that the subject is falsy (loosely false).
     *
     * @return static
     */
    public function toBeFalsy(): static
    {
        return $this->match(
            fn($expected) => (bool) $expected === false,
            'Expected %s to be falsy',
            $this->subject,
            ['__fake' => 'false'],
        );
    }

    /**
     * Asserts that the subject is close to the given value within a delta.
     *
     * @param float $value expected value
     * @param float $delta maximum allowed difference
     * @return static
     */
    public function toBeCloseTo(float $value, float $delta = 0.00001): static
    {
        return $this->match(
            fn($expected) => abs($expected - $value) <= $delta,
            'Expected %s to be close to %s (delta %s)',
            $this->subject,
            $value,
            $delta,
            ['__fake' => self::exportValue($value)],
        );
    }

    /**
     * Asserts that the subject string or array has the given length.
     *
     * @param int $length expected length
     * @return static
     */
    public function toHaveLength(int $length): static
    {
        return $this->match(
            fn($expected) => is_string($expected) ? strlen($expected) === $length : count($expected) === $length,
            // The subject alone leaves the reader counting: an array of one array
            // prints as "[array(2)]", which reads like a length of 2. The length
            // asserted on is the one thing the message must state outright.
            'Expected %s to have length %s, has %s',
            $this->subject,
            $length,
            self::lengthOf($this->subject),
            ['__fake' => is_string($this->subject) ? "str_repeat('x', {$length})" : "array_fill(0, {$length}, null)"],
        );
    }

    /**
     * Asserts that the subject array contains an element loosely equal to the value.
     *
     * @param mixed $value value to search for (loose comparison)
     * @return static
     */
    public function toContainEqual(mixed $value): static
    {
        return $this->match(
            fn($expected) => is_array($expected) && in_array($value, $expected),
            'Expected %s to contain equal %s',
            $this->subject,
            $value,
        );
    }

    /**
     * Asserts that the subject object responds to the given method.
     *
     * @param string $method method name
     * @return static
     */
    public function toRespondTo(string $method): static
    {
        return $this->match(
            fn($expected) => is_object($expected) && method_exists($expected, $method),
            'Expected %s to respond to %s',
            $this->subject,
            $method,
        );
    }

    /**
     * Asserts that the subject satisfies a custom predicate.
     *
     * @param callable $predicate callback receiving the subject, returning bool
     * @return static
     */
    public function toSatisfy(callable $predicate): static
    {
        return $this->match(
            fn($expected) => $predicate($expected),
            'Expected %s to satisfy predicate',
            $this->subject,
        );
    }

    /**
     * Asserts that the subject response has status 200.
     * Works with Browser\Response and PSR-7 ResponseInterface.
     *
     * @return static
     */
    public function toBeOk(): static
    {
        $data = self::extractResponseData($this->subject);
        return $this->match(
            fn($expected) => ($d = self::extractResponseData($expected)) !== null && $d['status'] === 200,
            'Expected response to be OK (200), got %s',
            $data['status'] ?? 'non-Response',
        );
    }

    /**
     * Asserts that the subject response has status 400.
     * Works with Browser\Response and PSR-7 ResponseInterface.
     *
     * @return static
     */
    public function toBeBad(): static
    {
        $data = self::extractResponseData($this->subject);
        return $this->match(
            fn($expected) => ($d = self::extractResponseData($expected)) !== null && $d['status'] === 400,
            'Expected response to be Bad Request (400), got %s',
            $data['status'] ?? 'non-Response',
        );
    }

    /**
     * Asserts that the subject response has the given HTTP status code.
     * Works with Browser\Response and PSR-7 ResponseInterface.
     *
     * @param int $code expected HTTP status code
     * @return static
     */
    public function toHaveStatus(int $code): static
    {
        $data = self::extractResponseData($this->subject);
        return $this->match(
            fn($expected) => ($d = self::extractResponseData($expected)) !== null && $d['status'] === $code,
            'Expected response status %s, got %s',
            $code,
            $data['status'] ?? 'non-Response',
        );
    }

    /**
     * Asserts that a dot-notation path in the response JSON body equals the expected value.
     * Works with Browser\Response and PSR-7 ResponseInterface.
     *
     * @param string $path dot-notation path (e.g. 'data.user.name')
     * @param mixed $expected expected value at that path
     * @return static
     */
    public function toHavePath(string $path, mixed $expected): static
    {
        return $this->match(
            function ($subject) use ($path, $expected) {
                $data = self::extractResponseData($subject);
                if ($data === null) {
                    return false;
                }
                $current = $data['json'];
                foreach (explode('.', $path) as $segment) {
                    if (!is_array($current) || !array_key_exists($segment, $current)) {
                        return false;
                    }
                    $current = $current[$segment];
                }
                return $current === $expected;
            },
            'Expected JSON path "%s" to be %s',
            $path,
            $expected,
        );
    }

    /**
     * Asserts that the subject response has a given header, optionally matching a value.
     * Works with Browser\Response and PSR-7 ResponseInterface.
     *
     * @param string $name header name (e.g. 'Content-Type')
     * @param string|null $value expected header value (null = just check existence)
     * @return static
     */
    public function toHaveHeader(string $name, ?string $value = null): static
    {
        $data = self::extractResponseData($this->subject);
        $actual = $data['headers'][$name] ?? null;
        return $this->match(
            function ($expected) use ($name, $value) {
                $d = self::extractResponseData($expected);
                if ($d === null || !array_key_exists($name, $d['headers'])) {
                    return false;
                }
                if ($value !== null && $d['headers'][$name] !== $value) {
                    return false;
                }
                return true;
            },
            $value !== null
                ? 'Expected header "%s" to be %s, got %s'
                : 'Expected response to have header "%s"',
            $name,
            ...($value !== null ? [$value, $actual ?? 'missing'] : []),
        );
    }

    /**
     * Asserts that the subject response is a redirect (3xx) to the given URL.
     * Checks the Location header against the expected path.
     * Works with Browser\Response and PSR-7 ResponseInterface.
     *
     * @param string $url expected redirect URL or path
     * @return static
     */
    public function toRedirectTo(string $url): static
    {
        $data = self::extractResponseData($this->subject);
        return $this->match(
            function ($expected) use ($url) {
                $d = self::extractResponseData($expected);
                if ($d === null) {
                    return false;
                }
                $status = $d['status'];
                if ($status < 300 || $status >= 400) {
                    return false;
                }
                $location = $d['headers']['Location'] ?? null;
                return $location === $url;
            },
            'Expected redirect to %s, got %s (status %s)',
            $url,
            $data['headers']['Location'] ?? 'no Location header',
            $data['status'] ?? 'non-Response',
        );
    }

    /**
     * Asserts that invoking the subject callable throws the specified exception.
     *
     * @param string $exceptionClass expected exception class
     * @param string|null $message expected exception message (exact match)
     * @return static
     */
    public function toThrow(string $exceptionClass, ?string $message = null): static
    {
        return $this->match(
            function ($expected) use ($exceptionClass, $message) {
                try {
                    $expected();
                    return false;
                } catch (\Throwable $e) {
                    if (!($e instanceof $exceptionClass)) {
                        return false;
                    }
                    if ($message !== null && $e->getMessage() !== $message) {
                        return false;
                    }
                    return true;
                }
            },
            'Expected callable to throw %s',
            $exceptionClass,
        );
    }

    /**
     * Evaluates a matcher against the subject, handling negation and dispatching
     * the match event through EventfulExpectation.
     *
     * @param Closure $match callback receiving the subject, returning bool
     * @param mixed ...$message format string followed by values, optionally ending with a fake expression array
     */
    private function match($match, ...$message): static
    {
        // The matcher name is the public method that called this chokepoint
        // (e.g. toBe); __call-based custom/predicate matchers stay anonymous.
        $caller = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function'] ?? null;
        $matcher = $caller !== null && !in_array($caller, ['__call', 'match'], true) ? $caller : null;
        $negated = $this->negated;

        $fakeExpression = null;
        // Extract fakeExpression if last argument is an array with '__fake' key
        if (!empty($message) && is_array(end($message)) && array_key_exists('__fake', end($message))) {
            $fakeArg = array_pop($message);
            $fakeExpression = $fakeArg['__fake'];
        }

        if ($this->negated) {
            $originalMatch = $match;
            $match = fn($expected) => !$originalMatch($expected);
            if (is_string($message[0])) {
                $message[0] = str_replace(' to ', ' not to ', $message[0]);
            }
            $fakeExpression = null; // negated matchers don't produce fakes
        }
        $this->eventCreator?->createMatchEvent($match, $message[0], $fakeExpression, $matcher, $negated, ...array_slice($message, 1));
        return $this;
    }

    /**
     * Replaces %s placeholders in the message with formatted value representations.
     *
     * @param string $message template with %s placeholders
     * @param mixed ...$values values to substitute
     * @return string formatted message
     */
    public static function formatMessage(string $message, mixed ...$values): string
    {
        foreach ($values as $value) {
            $message = match (gettype($value)) {
                'object' => self::replaceOne('%s', get_class($value) . '#' . spl_object_id($value), $message),
                'string' => self::replaceOne('%s', '"' . self::clip($value) . '"', $message),
                'boolean' => self::replaceOne('%s', $value ? 'true' : 'false', $message),
                'array' => self::replaceOne('%s', self::formatArray($value), $message),
                'NULL' => self::replaceOne('%s', 'null', $message),
                'double' => self::replaceOne('%s', self::formatFloat($value), $message),
                default => self::replaceOne('%s', (string) $value, $message)
            };
        }
        return $message;
    }

    /**
     * A float as a failure message must show it: keeping the fraction, so a
     * strict comparison that failed on type alone does not read as a tautology
     * ("Expected 90 to be 90"), and at full precision, so the one that failed on
     * a rounding tail says so instead of tidying itself up to 0.3.
     */
    private static function formatFloat(float $value): string
    {
        return is_finite($value) ? var_export($value, true) : (string) $value;
    }

    /**
     * How long a subject is in the terms toHaveLength asserts on: characters for
     * a string, elements for anything countable, and "no length" for a subject
     * that has none (the matcher fails it either way, and the message says why).
     */
    private static function lengthOf(mixed $subject): int|string
    {
        return match (true) {
            is_string($subject) => strlen($subject),
            is_countable($subject) => count($subject),
            default => 'no length',
        };
    }

    /**
     * A string as the failure headline shows it: short single-line values
     * verbatim, anything multiline or overlong clipped to a first-line
     * excerpt. The full values ride the expected/actual detail, so the
     * headline never repeats a wall of captured output.
     */
    private static function clip(string $value): string
    {
        $firstLine = strtok(ltrim($value), "\n");
        $excerpt = $firstLine === false ? '' : trim($firstLine);

        if ($excerpt === $value && mb_strlen($excerpt) <= 60) {
            return $value;
        }

        if (mb_strlen($excerpt) > 60) {
            $excerpt = mb_substr($excerpt, 0, 59);
        }

        return $excerpt . '…';
    }

    /**
     * Renders an array for a failure message, element-safe: objects appear as
     * Class#id and nested arrays by size, so a failing expectation on a list
     * of objects reports the failure instead of crashing the formatter.
     *
     * @param array<array-key, mixed> $value
     */
    private static function formatArray(array $value): string
    {
        $parts = array_map(static fn(mixed $item): string => match (gettype($item)) {
            'object' => get_class($item) . '#' . spl_object_id($item),
            'array' => 'array(' . count($item) . ')',
            'string' => '"' . $item . '"',
            'boolean' => $item ? 'true' : 'false',
            'NULL' => 'null',
            'double' => self::formatFloat($item),
            default => (string) $item,
        }, $value);

        return '[' . implode(', ', $parts) . ']';
    }

    /**
     * Replaces the first occurrence of a pattern in the string.
     *
     * @param string $pattern substring to find
     * @param string $replace replacement value
     * @param string $string source string
     * @return string string with first occurrence replaced
     */
    private static function replaceOne(string $pattern, string $replace, string $string): string
    {
        $pos = strpos($string, $pattern);
        if ($pos === false) {
            return $string;
        }
        return substr($string, 0, $pos) . $replace . substr($string, $pos + strlen($pattern));
    }

    /**
     * Extracts status, body, headers, and json from a response object.
     * Supports Browser\Response and PSR-7 ResponseInterface (when available).
     *
     * @return array{status: int, body: string, headers: array<string, string>, json: array<string, mixed>}|null
     */
    private static function extractResponseData(mixed $subject): ?array
    {
        if ($subject instanceof Response) {
            return [
                'status' => $subject->status,
                'body' => $subject->body,
                'headers' => $subject->headers,
                'json' => $subject->json,
            ];
        }

        if (interface_exists('Psr\Http\Message\ResponseInterface', false)
            && $subject instanceof \Psr\Http\Message\ResponseInterface) {
            $body = (string) $subject->getBody();
            return [
                'status' => $subject->getStatusCode(),
                'body' => $body,
                'headers' => array_map(fn(array $v) => implode(', ', $v), $subject->getHeaders()),
                'json' => json_decode($body, true) ?? [],
            ];
        }

        return null;
    }

    /**
     * Converts a value to its PHP source code representation for --fake code generation.
     *
     * @param mixed $value value to export
     * @return string PHP source code literal
     */
    private static function exportValue(mixed $value): string
    {
        if (is_string($value)) {
            return "'" . addslashes($value) . "'";
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_null($value)) {
            return 'null';
        }
        if (is_array($value)) {
            return var_export($value, true);
        }
        return var_export($value, true);
    }
}
