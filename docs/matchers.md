# Matchers

Matchers are methods on the `Expectation` object returned by `expect()`. They assert a condition about the subject.

All matchers are chainable -- they return the `Expectation` instance, so you can write `expect($x)->toBe(1)->toBeLike('1')`.

## Identity & Equality

### `toBe(mixed $expected)`

Strict identity comparison (`===`).

```php
expect(42)->toBe(42);        // passes
expect('42')->toBe(42);      // fails (string !== int)
```

### `toBeLike(mixed $expected)`

Loose equality comparison (`==`).

```php
expect('42')->toBeLike(42);  // passes
expect(0)->toBeLike(false);  // passes
```

## Boolean Matchers

### `toBeTrue()`

Asserts the subject is strictly `true`.

```php
expect(true)->toBeTrue();    // passes
expect(1)->toBeTrue();       // fails
```

### `toBeFalse()`

Asserts the subject is strictly `false`.

```php
expect(false)->toBeFalse();  // passes
```

### `toBeNull()`

Asserts the subject is `null`.

```php
expect(null)->toBeNull();    // passes
```

### `toBeEmpty()`

Asserts the subject is empty (works with arrays, strings, and countables).

```php
expect([])->toBeEmpty();     // passes
expect('')->toBeEmpty();     // passes
```

### `toBeTruthy()`

Asserts the subject is loosely truthy (`(bool) $subject === true`).

```php
expect(1)->toBeTruthy();     // passes
expect('0')->toBeTruthy();   // fails
```

### `toBeFalsy()`

Asserts the subject is loosely falsy (`(bool) $subject === false`).

```php
expect(0)->toBeFalsy();      // passes
expect('')->toBeFalsy();     // passes
```

## Type Matchers

### `toBeAnInstanceOf(string $class)`

Checks that the subject is an instance of the given class or interface.

```php
expect($calc)->toBeAnInstanceOf(Calculator::class);
expect($list)->toBeAnInstanceOf(Countable::class);
```

### `toBeOfType(string $type)`

Checks the subject's type via `get_debug_type()` or `instanceof`.

```php
expect(42)->toBeOfType('int');
expect('hello')->toBeOfType('string');
expect([])->toBeOfType('array');
```

### `toBeCallable()`

Asserts the subject is callable.

```php
expect(fn () => true)->toBeCallable();
expect('strlen')->toBeCallable();
```

## String Matchers

### `toContain(mixed $needle)`

For strings, checks that the subject contains the substring. For arrays, checks the value is in the array.

```php
expect('hello world')->toContain('world');
expect([1, 2, 3])->toContain(2);
```

### `toStartWith(string $prefix)`

Asserts the string starts with the given prefix.

```php
expect('hello')->toStartWith('hel');
```

### `toEndWith(string $suffix)`

Asserts the string ends with the given suffix.

```php
expect('hello')->toEndWith('llo');
```

### `toMatch(string $pattern)`

Asserts the string matches a regular expression.

```php
expect('abc123')->toMatch('/^\w+$/');
expect('hello@example.com')->toMatch('/@/');
```

## Collection Matchers

### `toHaveCount(int $count)`

Checks that the subject (array or countable) has the expected number of elements.

```php
expect([1, 2, 3])->toHaveCount(3);
expect([])->toHaveCount(0);
```

### `toHaveKey(string|int $key)`

Asserts the array has the given key.

```php
expect(['name' => 'Alice'])->toHaveKey('name');
expect([10, 20])->toHaveKey(0);
```

### `toHaveLength(int $length)`

Asserts the subject string or array has the given length.

```php
expect('hello')->toHaveLength(5);
expect([1, 2, 3])->toHaveLength(3);
```

### `toContainEqual(mixed $value)`

Asserts the array contains an element loosely equal (`==`) to the value.

```php
expect([1, 2, 3])->toContainEqual('2');   // passes (loose comparison)
```

## Comparison Matchers

### `toBeGreaterThan(int|float $value)`

Asserts the subject is greater than the given value.

```php
expect(10)->toBeGreaterThan(5);
```

### `toBeLessThan(int|float $value)`

Asserts the subject is less than the given value.

```php
expect(3)->toBeLessThan(10);
```

### `toBeGreaterThanOrEqualTo(mixed $value)`

Asserts the subject is greater than or equal to the given value.

```php
expect(5)->toBeGreaterThanOrEqualTo(5);
```

### `toBeLessThanOrEqualTo(mixed $value)`

Asserts the subject is less than or equal to the given value.

```php
expect(5)->toBeLessThanOrEqualTo(5);
```

### `toBeBetween(mixed $min, mixed $max)`

Asserts the subject is between `$min` and `$max`, inclusive.

```php
expect(5)->toBeBetween(1, 10);
```

### `toBeCloseTo(float $value, float $delta = 0.00001)`

Asserts the subject is within `$delta` of the given float — for comparing
floating-point numbers without exact-equality surprises.

```php
expect(0.1 + 0.2)->toBeCloseTo(0.3);
expect(3.14159)->toBeCloseTo(3.14, 0.01);
```

## Object Matchers

### `toHaveProperty(string $property)`

Asserts the object has a public property with the given name.

```php
$obj = new stdClass();
$obj->name = 'Alice';
expect($obj)->toHaveProperty('name');
```

### `toRespondTo(string $method)`

Asserts the object has (responds to) the given method.

```php
expect(new Calculator())->toRespondTo('add');
```

### `toSatisfy(callable $predicate)`

Asserts the subject satisfies a custom predicate — a callback that receives the
subject and returns `true` (pass) or `false` (fail). Handy for one-off checks
that don't warrant a named custom matcher.

```php
expect(42)->toSatisfy(fn ($n) => $n % 2 === 0);
```

## Exception Matcher

### `toThrow(string $exceptionClass, ?string $message = null)`

Asserts that calling the subject (a callable) throws the expected exception.

```php
expect(fn () => throw new \RuntimeException('boom'))
    ->toThrow(\RuntimeException::class);

// With message check:
expect(fn () => throw new \RuntimeException('boom'))
    ->toThrow(\RuntimeException::class, 'boom');
```

## HTTP Response Matchers

For asserting on HTTP responses. All work with the built-in `Browser\Response`
(see [Browser Testing](browser.md)) and any PSR-7 `ResponseInterface`.

### `toBeOk()`

Asserts the response status is `200`.

```php
expect($response)->toBeOk();
```

### `toBeBad()`

Asserts the response status is `400`.

```php
expect($response)->toBeBad();
```

### `toHaveStatus(int $code)`

Asserts the response has the given HTTP status code.

```php
expect($response)->toHaveStatus(201);
```

### `toHavePath(string $path, mixed $expected)`

Asserts a dot-notation path in the response's JSON body equals the expected value.

```php
expect($response)->toHavePath('data.user.name', 'Alice');
```

### `toHaveHeader(string $name, ?string $value = null)`

Asserts the response has the given header — optionally matching a value.

```php
expect($response)->toHaveHeader('Content-Type');
expect($response)->toHaveHeader('Content-Type', 'application/json');
```

### `toRedirectTo(string $url)`

Asserts the response is a redirect (`3xx`) whose `Location` header matches the URL.

```php
expect($response)->toRedirectTo('/login');
```

## Negation

Use `not()` to negate any matcher:

```php
expect(42)->not()->toBe(43);
expect('hello')->not()->toBeEmpty();
expect([])->not()->toContain(99);
expect($mock->method())->not()->toBeCalled();
```

`not()` works with all matchers including mock matchers.

## Custom Matchers

Register custom matchers with `addMatcher()`:

```php
addMatcher(
    'toBePrime',
    function (int $n): bool {
        if ($n < 2) return false;
        for ($i = 2; $i * $i <= $n; $i++) {
            if ($n % $i === 0) return false;
        }
        return true;
    },
    'Expected %s to be a prime number'
);

expect(7)->toBePrime();       // passes
expect(4)->toBePrime();       // fails with: "Expected 4 to be a prime number"
expect(4)->not()->toBePrime(); // passes
```

The callback receives the subject as its first argument plus any additional arguments passed to the matcher. Return `true` for pass, `false` for fail.

## Mock Matchers

When `expect()` receives a mock method call, it returns a `Mock\Expectation` with additional matchers. See [Mocking](mocking.md) for full details.

### `toBeCalled()`

Verifies the method was called at least once.

### `toHaveBeenCalled()`

Alias for `toBeCalled()`.

### `toBeCalledWith(...$args)`

Verifies the method was called with specific arguments. Supports argument matchers: `any()`, `type('string')`, `callback(fn)`.

### `toBeCalledTimes(int $count)`

Verifies the exact number of times a method was called.

### `toReturn(mixed $value)`

Stubs the mock method to return a value.

## How Matchers Work Internally

Each matcher calls `EventfulExpectation::createMatchEvent()`, which:

1. Dispatches an `ExpectationStarted` event
2. Creates a lazy `MatchCreated` event containing a closure
3. The closure evaluates the match and returns a `MatchResult` (passed or failed)
4. The `ExampleSubscriber` collects these match closures and executes them when the example finishes

Failed matches carry a `Detail\Failed` object with expected/actual values, formatted message, file path, line number, and surrounding source code.

## Failure Output

```
  ✘ returns the sum of two numbers

  Failure: Expected 5 to be 6

    expected: 6
         got: 5

  12  |     it("returns the sum of two numbers", function() {
  13  |         $calc = new Calculator();
> 14  |         expect($calc->add(2, 3))->toBe(6);
  15  |     });

  at spec/App/Calculator.spec.php:14
```
