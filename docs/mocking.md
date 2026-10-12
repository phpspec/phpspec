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
made from the code under spec, fails the example unless a `toHaveBeenCalled()`
after the act checks it. `dummy()` makes a
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

The value has to be one the real method could return, held strictly: a method
returning `int` is not given `'42'`, and one returning `void` is not given
anything, its call is declared with `allow()` alone. A value it could never
return is refused where the spec sets it:

```
App\Counter::count() returns int, so it cannot return string "three".
```

What a `toReturnUsing()` callback answers is held the same way when the call
is made.

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

### Stubs answer in the order they were declared

Each call the code under spec makes is answered by the first stub that matches
it and has not answered yet; once every matching stub has answered, the last
one answers on. Two stubs of the same call answer one call after the other:

```php
allow($repo->find(1))->toThrow(new \RuntimeException('Busy'));
allow($repo->find(1))->toReturn('Alice'); // the second find(1), and every one after
```

Stubs that overlap are used in order too: a `find(42)` declared after a
`find(any())` answers only once `find(any())` has. A stub every example needs
belongs in `beforeEach`; one an example would replace belongs in that example.

The spec's own calls use no stub up and have its exception withheld, so the
second `allow($repo->find(1))` above declares the call rather than throwing.
They see the value the next stub would give, and `toThrow()` sees the
exception it would throw: `expect(fn () => $repo->find(1))->toThrow(...)`.

### What an unstubbed call returns

A method nobody stubbed returns a default for its return type: `''`, `0`,
`false`, `[]`, `null` where allowed, a double of a class or interface it
returns, an empty `iterable`, a `Closure`, an empty `Generator`, `true` or
`false` for those types, the double itself for `static`, `self` and `parent`. A method returning a final class hands the spec an
instance of it built without its constructor, for `allow()` to replace; a
`dummy()` has nobody about to replace it and says so instead.

## Calls the spec never declared

A double is strict towards the code under spec. A call that no `allow()` or
`expect()` declared, made from anywhere outside the spec code, is held, as a
stub's exception is: the code under spec carries on with the type's default,
and a `toHaveBeenCalled()` after the act claims the call it checks. A call
nothing claims fails the example when it ends, naming where it was made. The
spec code is every file PhpSpec loaded as a spec, step or
support file, wherever it sits, and whatever sits in the spec, features and
suite folders, or the folder of a path the run was given:

```
App\Stock::reserve("tea", 2) was called but not expected, at src/App/Checkout.php:8.
Declare the call with allow() or expect(), check it with toHaveBeenCalled() after the act, or make the double a dummy() if its calls do not matter.
```

A call is declared by the arguments it was written with, read as PHP reads
the call: `any()` stands for one argument whose value does not matter,
`cetera()` for the rest, and a call that leaves out an argument with a default
is the call with that default. `allow()` declares on its own, a stub is
optional:

```php
allow($stock->reserve('tea', 2));                 // reserve('tea', 2) may be called, returns false
allow($stock->reserve('tea', 2))->toReturn(true); // and returns true
expect($stock->reserve(cetera()))->toBeCalled();  // any reserve() may be called, and must be
```

`reserve()` is a call with no arguments, which no object taking two could be
given, so it is refused where the spec writes it:

```
App\Stock::reserve() takes 2 arguments (string $item, int $quantity), so a call written with none can never be made.
Write the arguments it is called with, any() for one whose value does not matter, or cetera() for the rest.
```

An argument of a type the method does not take is refused the same way. The
code under spec calling a double with too few arguments, or with an argument
of the wrong type, fails as it would on the real object, its argument coerced
or held strictly as the file it calls from declares:

```
App\Stock::reserve(): Argument #2 ($quantity) must be of type int, string given, called in src/App/Checkout.php on line 11
```

The spec file itself is free to call anything: that is how `allow()` and
`expect()` reach the call they declare. A collaborator whose calls do not
matter is a `dummy()`. A double that was injected by type hint, which
`dummy()` cannot make, is left lenient for the rest by ending a stub in
`ignoreOthers()`:

```php
beforeEach(function (Filesystem $fs) {
    allow($fs->exists(any()))->toReturn(false);
    allow($fs->isDir(any()))->toReturn(false)->ignoreOthers();
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
else, `save(any())` by a `save()` of anything. The declaration also makes the
call expected, so the code under spec may make it:

```php
it('calls save', function (UserRepository $repo) {
    expect($repo->save($user))->toBeCalled();

    (new Registration($repo))->register($user);
});

it('saves something', function (UserRepository $repo) {
    expect($repo->save(any()))->toBeCalled();

    (new Registration($repo))->register($anyUser);
});
```

### `toHaveBeenCalled()`

Asked after the act, judged at once from the calls recorded so far. Nothing
needs declaring before the act: the call is held until the check claims it,
and the code under spec gets the type's default meanwhile.

```php
it('saves the user', function (UserRepository $repo) {
    (new Registration($repo))->register($user);

    expect($repo->save($user))->toHaveBeenCalled();
});
```

Negated, it asks that the call was not made: `expect($repo->save($other))->not()->toHaveBeenCalled()`.
A check that fails names every call the method received, and claims them, so
none is refused again when the example ends.


### Counting calls

`toBeCalled()` takes how many times, counting the calls that match the
arguments the call was written with; a call that must not happen is
`not()->toBeCalled()`:

```php
expect($logger->log('test'))->toBeCalled()->once();
expect($logger->log('test'))->toBeCalled()->twice();
expect($logger->log('test'))->toBeCalled()->exactly(3)->times();
expect($logger->log('test'))->toBeCalled()->atLeast(2)->times();
expect($logger->log('test'))->toBeCalled()->atMost(2)->times();
expect($logger->log('other'))->not()->toBeCalled();
```

## Argument Matchers

An argument written as a value matches by value: a scalar strictly, an object
when it equals the one written (`new Money(900)` matches any `Money(900)`,
whichever instance carries it), an array element by element on the same rule.
For anything looser, write a matcher in the call:

### `any()`

Matches any single argument:

```php
expect($logger->log(any()))->toBeCalled();
```

### `type(string $type)`

Matches an argument of a specific type:

```php
expect($repo->save(type('object')))->toBeCalled();
```

### `callback(Closure $fn)`

Matches using a custom callback:

```php
expect($repo->save(callback(fn ($arg) => $arg->name === 'Alice')))->toBeCalled();
```

### `satisfy(Closure $fn)`

Matches a single argument against a predicate, as `callback()` does, and reads
well alongside the others:

```php
expect($repo->save(satisfy(fn ($u) => $u->isActive())))->toBeCalled();
```

### `anInstanceOf(string $class)`

Matches an argument that is an instance of the given class or interface:

```php
expect($bus->dispatch(anInstanceOf(Command::class)))->toBeCalled();
```

### `arrayIncluding(array $subset)`

Matches an array argument that contains (at least) the given key/value subset:

```php
expect($api->send(arrayIncluding(['type' => 'order'])))->toBeCalled();
```

### `startWith(string $prefix)`

Matches a string argument that starts with the given prefix:

```php
expect($logger->log(startWith('ERROR:')))->toBeCalled();
```

### `cetera()`

Matches the remaining arguments, none or more: the "and so on" wildcard. Nothing
can follow it:

```php
expect($logger->log('hello', cetera()))->toBeCalled();
```

## Negation

All mock matchers support negation via `not()`:

```php
expect($repo->delete(1))->not()->toBeCalled();
expect($logger->log('other'))->not()->toBeCalled();
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

## Coming from Prophecy

A call is written as in Prophecy, as the call itself, and read the same
way: `$repo->find()` is a call with no arguments, never `find(1)`. Where the
method requires an argument, that call is refused where the spec writes it,
so a `not()->toBeCalled()` can never pass for a call that could not happen.

| Prophecy | PhpSpec 9 |
|---|---|
| `$repo->find(1)->willReturn('Alice')` | `allow($repo->find(1))->toReturn('Alice')` |
| `$repo->find(1)->willThrow(NotFound::class)` | `allow($repo->find(1))->toThrow(NotFound::class)` |
| `$repo->find(1)->will(fn ($args) => ...)` | `allow($repo->find(1))->toReturnUsing(fn ($id) => ...)` |
| `$repo->save($user)->shouldBeCalled()` | `expect($repo->save($user))->toBeCalled()` |
| `$repo->save($user)->shouldBeCalledTimes(2)` | `expect($repo->save($user))->toBeCalled()->exactly(2)->times()` |
| `$repo->save(Argument::cetera())->shouldNotBeCalled()` | `expect($repo->save(cetera()))->not()->toBeCalled()` |
| `$repo->save($user)->shouldHaveBeenCalled()` | `expect($repo->save($user))->toHaveBeenCalled()` |
| `Argument::any()`, `Argument::cetera()` | `any()`, `cetera()` |
| `Argument::type('string')` | `type('string')` |
| `Argument::that(fn ($user) => ...)` | `satisfy(fn ($user) => ...)` |

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
