# Mocking

PhpSpec includes a built-in mocking system -- no external libraries required. It generates test doubles at runtime using reflection and `eval()`.

## Creating Doubles

### Using `mock()`

Use the global `mock()` function:

```php
$double = mock(UserRepository::class);
```

This generates a dynamic subclass (or implementation, for interfaces) that:

- Extends the original class (or implements the interface)
- Overrides all methods to track calls
- Auto-generates constructor dependencies recursively (using mocks/defaults)

### Type-Hinted Mock Injection

Both `it()` and `let()` closures support type-hinted parameters that are automatically resolved as mocks:

```php
describe(UserService::class, function () {
    // Injected via let()
    let('service', fn (UserRepository $repo) => new UserService($repo));

    // Injected via it()
    it('finds a user', function (UserRepository $repo) {
        allow($repo->find(1))->toReturn(['name' => 'Alice']);
        $service = new UserService($repo);
        expect($service->getDisplayName(1))->toBe('Alice');
    });
});
```

Any parameter with a class or interface type hint will be automatically resolved via `mock()`. This is the preferred way to create mocks.

## Stubbing Return Values

### `allow()` + `toReturn()`

The `allow()` function stubs a method to return a specific value:

```php
it('returns user data', function (UserRepository $repo) {
    allow($repo->find(1))->toReturn(['name' => 'Alice']);

    $result = $repo->find(1);
    expect($result)->toBe(['name' => 'Alice']);
});
```

### `allow()` + `toThrow()`

Stub a method to throw an exception:

```php
it('handles errors', function (UserRepository $repo) {
    allow($repo->find(999))->toThrow(new \RuntimeException('Not found'));

    expect(fn () => $repo->find(999))->toThrow(\RuntimeException::class);
});
```

## Verifying Method Calls

### `toBeCalled()`

Verifies the method was called at least once:

```php
it('calls save', function (UserRepository $repo) {
    expect($repo->save($user))->toBeCalled();
    $repo->save($user);
});
```

### `toHaveBeenCalled()`

Alias for `toBeCalled()`:

```php
expect($repo->save($user))->toHaveBeenCalled();
```

### `toBeCalledWith(...$args)`

Verifies the method was called with specific arguments:

```php
it('saves with correct data', function (Logger $logger) {
    expect($logger->log('hello', 'info'))->toBeCalledWith('hello', 'info');
    $logger->log('hello', 'info');
});
```

### `toBeCalledTimes(int $count)`

Verifies the exact number of times a method was called:

```php
it('calls exactly twice', function (Logger $logger) {
    expect($logger->log('test'))->toBeCalledTimes(2);
    $logger->log('test');
    $logger->log('test');
});
```

## Argument Matchers

Use argument matchers with `toBeCalledWith()` for flexible argument matching:

### `any()`

Matches any single argument:

```php
expect($logger->log('hello'))->toBeCalledWith(any());
```

### `type(string $type)`

Matches an argument of a specific type:

```php
expect($repo->save($user))->toBeCalledWith(type('object'));
```

### `callback(Closure $fn)`

Matches using a custom callback:

```php
expect($repo->save($user))->toBeCalledWith(callback(fn ($arg) => $arg->name === 'Alice'));
```

## Negation

All mock matchers support negation via `not()`:

```php
expect($repo->delete(1))->not()->toBeCalled();
expect($logger->log('test'))->not()->toBeCalledWith('other');
```

## Using Mocks with `let()`

```php
describe(Notifier::class, function () {
    let('mailer', fn () => mock(Mailer::class));
    let('notifier', fn () => new Notifier($this->mailer));

    it('sends an email', function () {
        expect($this->mailer->send('hello'))->toBeCalled();
        $this->notifier->notify('hello');
    });
});
```

Or using type-hinted injection in `let()`:

```php
describe(Notifier::class, function () {
    let('notifier', fn (Mailer $mailer) => new Notifier($mailer));

    it('sends an email', function () {
        // $this->mailer is available from let() injection
    });
});
```

## Supported Types

The mock system handles:

- **Classes** -- generates a subclass
- **Interfaces** -- generates an implementation
- **Nullable types** (`?Foo`) -- wraps return with null handling
- **Union types** (`Foo|Bar`) -- uses the first mockable type
- **Intersection types** (`Foo&Bar`) -- creates a combined implementation
- **Enum types** -- returns the first case as default
- **Scalar return types** (`string`, `int`, `float`, `bool`, `array`) -- returns type-appropriate defaults

## Limitations

- Final classes cannot be mocked (PHP reflection limitation)
- Readonly classes cannot be mocked
- Constructor dependency auto-resolution handles classes, interfaces, strings, arrays, and scalars
- Circular type dependencies are capped at depth 3
