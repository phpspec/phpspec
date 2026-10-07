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

### Using `dummy()`

A double from `mock()` is strict: a call to it that the spec never declared,
made from the code under spec, fails the example at once. `dummy()` makes a
lenient double for a collaborator whose calls do not matter, a logger passed
along for instance. It answers every call with a default for the return type
and still takes stubs:

```php
$logger = dummy(Logger::class);
```

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

Give it the very instance to throw, as above, or a class name and a message
and it builds one at the call: `toThrow(\RuntimeException::class, 'Not found')`.

### What an unstubbed call returns

A method nobody stubbed returns a default for its return type: `''`, `0`,
`false`, `[]`, `null` where allowed, a double of a class or interface it
returns, an empty `iterable`, a `Closure`, an empty `Generator`, the double
itself for `static`. A method returning a final class has no stand-in: calling
it before it is stubbed says so. Return an interface, or make the class
non-final.

## Calls the spec never declared

A double is strict towards the code under spec. A call that no `allow()` or
`expect()` declared, made from anywhere outside the spec and features folders,
fails the example at once:

```
App\Stock::reserve("tea", 2) was called but not expected, at src/App/Checkout.php:8.
Declare the call with allow() or expect(), or make the double a dummy() if its calls do not matter.
```

A call is declared by the arguments it was written with; written bare, the
declaration covers any arguments. `allow()` declares on its own, a stub is
optional:

```php
allow($stock->reserve('tea', 2));                // reserve('tea', 2) may be called, returns false
allow($stock->reserve('tea', 2))->toReturn(true); // and returns true
expect($stock->reserve())->toBeCalled();         // any reserve() may be called, and must be
```

The spec file itself is free to call anything: that is how `allow()` and
`expect()` reach the call they declare. A collaborator whose calls do not
matter is a `dummy()`. A double that was injected by type hint, which
`dummy()` cannot make, is left lenient for the rest by ending a stub in
`ignoreOthers()`:

```php
beforeEach(function (Filesystem $fs) {
    allow($fs->exists())->toReturn(false);
    allow($fs->isDir())->toReturn(false)->ignoreOthers();
});
```

## Verifying Method Calls

Stub and verify the same call in any order: a stubbed call still records
that it was made, and its returned value still compares with the ordinary
matchers.

### `toBeCalled()`

Declared before the act, judged when the example ends: the method must be
called at least once by then. Arguments written in the expect call are part
of the promise: `save($user)` is not satisfied by a `save()` of something
else. Written without arguments, it means called at all. The declaration also
makes the call expected, so the code under spec may make it:

```php
it('calls save', function (UserRepository $repo) {
    expect($repo->save($user))->toBeCalled();

    (new Registration($repo))->register($user);
});

it('saves something', function (UserRepository $repo) {
    expect($repo->save())->toBeCalled();

    (new Registration($repo))->register($anyUser);
});
```

### `toHaveBeenCalled()`

Asked after the act, judged at once from the calls recorded so far. The call
has to be declared before the act, with `allow()`, or the code under spec is
refused when it makes it:

```php
it('saves the user', function (UserRepository $repo) {
    allow($repo->save($user));

    (new Registration($repo))->register($user);

    expect($repo->save($user))->toHaveBeenCalled();
});
```

Negated, it asks that the call was not made: `expect($repo->save($other))->not()->toHaveBeenCalled()`.

### `toBeCalledWith(...$args)`

Verifies the method was called with specific arguments:

```php
it('saves with correct data', function (Logger $logger) {
    expect($logger->log('hello', 'info'))->toBeCalledWith('hello', 'info');
    $logger->log('hello', 'info');
});
```

### `toBeCalledTimes(int $count)`

Verifies the exact number of times a method was called, counting only calls
that match the arguments written in the expect call (all calls when written
bare):

```php
it('calls exactly twice', function (Logger $logger) {
    expect($logger->log('test'))->toBeCalledTimes(2);
    $logger->log('test');
    $logger->log('test');
});
```

## Argument Matchers

An argument written as a value matches by value: a scalar strictly, an object
when it equals the one written (`new Money(900)` matches any `Money(900)`,
whichever instance carries it), an array element by element on the same rule.
For anything looser, use a matcher:

Use argument matchers, in the expect call or in `toBeCalledWith()`, for
flexible argument matching:

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

### `satisfy(Closure $fn)`

Matches a single argument against a predicate — like `callback()`, reads well
alongside the others:

```php
expect($repo->save($user))->toBeCalledWith(satisfy(fn ($u) => $u->isActive()));
```

### `anInstanceOf(string $class)`

Matches an argument that is an instance of the given class or interface:

```php
expect($bus->dispatch($cmd))->toBeCalledWith(anInstanceOf(Command::class));
```

### `arrayIncluding(array $subset)`

Matches an array argument that contains (at least) the given key/value subset:

```php
expect($api->send($payload))->toBeCalledWith(arrayIncluding(['type' => 'order']));
```

### `startWith(string $prefix)`

Matches a string argument that starts with the given prefix:

```php
expect($logger->log($line))->toBeCalledWith(startWith('ERROR:'));
```

### `noArgs()`

Matches a call made with no arguments at all:

```php
expect($service->flush())->toBeCalledWith(noArgs());
```

### `cetera()`

Matches all remaining arguments (zero or more) — the "and so on" wildcard. Must
be the last matcher in the expected list:

```php
expect($logger->log('hello', 'x', 'y'))->toBeCalledWith('hello', cetera());
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
