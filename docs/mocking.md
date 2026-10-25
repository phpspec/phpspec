# Mocking

PhpSpec includes a built-in mocking system, no external libraries required. It generates test doubles at runtime using reflection and `eval()`.

## Creating Doubles

### Using `mock()`

Use the global `mock()` function:

```php
$double = mock(UserRepository::class);
```

This generates a dynamic subclass (or implementation, for interfaces) that:

- Extends the original class (or implements the interface)
- Overrides all methods to track calls
- Is made without running the class's constructor

Some built-in interfaces PHP lets no plain class implement, so a double of an
interface built on one is built the way PHP allows:

| Built on | The double | |
|---|---|---|
| `Traversable` | is an `IteratorAggregate` | its `getIterator()` iterates nothing unless stubbed: `allow($bag->getIterator())->toReturn(new ArrayIterator([...]))` |
| `Throwable` | extends `Exception` | it can be thrown; `getMessage()` and the other methods `Exception` declares final answer as an `Exception` does, and cannot be stubbed |
| `DateTimeInterface` | extends `DateTimeImmutable` | its methods are stubbed as any other |
| `UnitEnum` | cannot be made | only an enum can implement it, so `mock()` and `dummy()` refuse it, saying so |

An extension may refuse a plain class too: PhpSpec asks PHP once per run, and
a double PHP refuses is refused with PHP's reason:

```
Cannot create a test double for interface App\Labelled. Only an enum can implement UnitEnum.
```

A double of several interfaces, such as what a method returning `Wide&Narrow`
answers, takes every call each of them declares, and returns what every
declared return type admits. A method returning `Traversable` in one and
`ArrayIterator` in the other returns `ArrayIterator`, and one returning
`Countable` and `Traversable` returns `Countable&Traversable`. The same holds
between an interface and the base a double of it is built on. Where no value
can satisfy every return type, such as `int` and `string`, or two classes
neither extends, the method is refused when called:

```
App\Sized::size() returns int and App\Named::size() returns string, so no value can be returned for both.
```

A class named with interfaces, such as what a method returning `Base&HasValue`
answers, keeps what it is: its final methods, and an `Error` or a `DateTime` it
already extends where an interface needs a `Throwable` or a
`DateTimeInterface`, the serialization methods it has, and the iterator it
already is. A subclass of it adds what it can for a `Traversable` interface: an
`IteratorAggregate` where its `getIterator()` allows one, so stubbing
`getIterator()` rules `foreach`, else an `Iterator`. A double iterating as an
`Iterator` is refused where its `valid()` is final, or resolves to a type
without `false`, since no double of it could end the iteration. What a dummy
aggregate's `getIterator()` answers iterates as empty: an aggregate whose own
`getIterator()` leads to an `Iterator` without coming back to an aggregate on
the way, else an empty `Iterator`, trying each type of a union in turn. Where
none can be, iterating the dummy is refused, saying what keeps it from one, and
a `mock()` can still stub the call. A class
declaring static, and not private, a method an interface declares on an object
cannot be that interface. A class is the class it
names however it is spelled, through an alias or in another case, and an
intersection one of its types already satisfies is answered as that type.

A double inherits a static method the class implements. One it must implement,
abstract in an interface or a class, is refused when called, since a double
answers on an object, not on its class:

```
App\FromData::fromData() is static, so no double can answer it.
```

A method a type declares only by an `@method` tag, on itself, a parent class
or an interface, as libraries do for a method they keep for a later major
version, is a method of its double too: stubbed, expected and spied on like
any other. It takes any arguments and returns any value, since the tag's types
are the docblock's rather than PHP's. A tag reading `@method static Foo
make()` declares a static one, refused when called like the one above;
`@method static make()` is a method on the object that returns `static`.

Declarations no class can implement together, a parameter one takes by
reference and another by value, a method one declares static and another on an
object, or a method narrowing one the base declares final, are refused: by `mock()` and `dummy()` at once, and as a return type only
when the method returning it is called:

```
Cannot create a test double for interface App\Failure. App\Failure::getPrevious() narrows a method Exception declares final.
```

A double of a class never runs the class's constructor, which would run the
application's initialization with arguments nobody gave. Its properties keep
the defaults they declare; a promoted property, or any other the constructor
sets, stays uninitialised, so a final method that reads one needs a real
instance instead. A double of an interface built on `DateTimeImmutable` or
`Exception` is initialised as that base is.

### Using `dummy()`

A double from `mock()` is strict: a call to it that the spec never declared,
made from the code under spec, fails the example unless a `toHaveBeenCalled()`
after the act checks it. `dummy()` makes a double that stands in for the real
thing with no behaviour, for a collaborator whose calls do not matter, a logger
passed along for instance:

```php
$logger = dummy(Logger::class);
```

Every call returns a default for its return type, and a method returning a
class or interface answers a dummy of it, so a chain the code under spec
follows is answered with defaults at any depth. A dummy takes no stub or
expectation, and neither does what it answered. A spec that needs one needs a
`mock()`, which ends a stub in `ignoreOthers()` when only some calls matter:

```
App\Book::pages() was stubbed on a dummy. Use mock() instead of dummy().
App\Page::title() was stubbed on what dummy App\Book::first()->next() answered. Use mock() instead of dummy() and stub App\Book::first().
```

### Type-Hinted Mock Injection

Both `it()` and `this()` closures support type-hinted parameters that are automatically resolved as mocks:

```php
describe(UserService::class, function () {
    // Injected via this()
    this('service', fn (UserRepository $repo) => new UserService($repo));

    // Injected via it()
    it('finds a user', function (UserRepository $repo) {
        allow($repo->find(1))->toReturn(['name' => 'Alice']);
        $service = new UserService($repo);
        expect($service->getDisplayName(1))->toBe('Alice');
    });
});
```

Any parameter with a class or interface type hint will be automatically resolved via `mock()`. This is the preferred way to create mocks. A parameter is always a double, the same for every closure of the example that names it alike; [Hooks](hooks.md#type-hinted-mock-injection) has which of them sit on `$this`.

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

`andReturn()` is another name for `toReturn()`.

The value has to be one the real method could return, held strictly: a method
returning `int` is not given `'42'`, and one returning `void` is not given
anything, its call is declared with `allow()` alone. A value it could never
return is refused where the spec sets it:

```
App\Counter::count() returns int, so it cannot return string "three".
```

What a `toReturnUsing()` callback answers is held the same way when the call
is made.

A callback is handed the call's arguments as PHP hands them to the real
method. One the method takes by reference reaches the callback as that
reference, so what the callback writes into it reaches the caller. A method
that returns by reference hands back the reference a callback returns by
reference. A value given to `toReturn()` is always handed back as a copy:

```php
allow($cache->get('key', $anything))->toReturnUsing(function (string $key, ?array &$metadata): string {
    $metadata = ['hit' => true];

    return 'value';
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
`false`, `[]`, `null` where allowed, an answer of the class or interface it
returns (of the first a union of them names, a built-in interface such as
`DateTimeInterface` or `Countable` included), an empty `iterable`, a
`Closure`, an empty `Generator`, `true` or `false` for those types, the double
itself for `static`, `self` and `parent`. A nullable class or interface of
your own answers a double of it, a nullable built-in interface `null`. A union
of your own classes and interfaces that allows `null` answers as it would
without `null` (`Page|Book|null` a `Page` double, `Book|Page|null` a `Book`
one), and `null` when none of them can have a double; one that also holds a
scalar or a built-in type answers `null`. A stub can still return `null`.
A method returning an interface no double can be (one only an enum can
implement) is refused when it is called, with the reason. A method returning a final class hands the spec an
instance of it built without its constructor, for `allow()` to replace. A
lenient double has nobody about to replace it, so it refuses the code under
spec, still handing the spec's own call the instance `allow()` needs. A type no
double nor instance can stand in for, such as an internal final class, is
refused, saying why.

An answer stands for the call that returned it, for `allow()` and `expect()`
to take. A lenient double, a `dummy()` or one that ignores others, answers a
dummy. A strict double's answer is not for use: a call on it is refused, naming
the call to stub, and the code under spec catching the refusal still fails the
example. A chain the code under spec follows is stubbed one call at a time,
each with a double of its own:

```php
allow($book->first())->toReturn($page);
allow($page->next())->toReturn($second);
```

```
App\Page::next() was called on what App\Book::first() answered, at src/App/Reader.php:8. Stub App\Book::first().
```

A spec that wants defaults from the answer returns a dummy, and the double
stays strict: `allow($book->first())->toReturn(dummy(App\Page::class))`.

## Calls the spec never declared

A double is strict towards the code under spec. A call that no `allow()` or
`expect()` declared and no `toHaveBeenCalled()` checks, made from anywhere
outside the spec code, fails the example. The spec code is every file PhpSpec loaded as a spec, step or
support file, wherever it sits, and whatever sits in the spec, features and
suite folders, or the folder of a path the run was given:

```
App\Stock::reserve("tea", 2) was called but not expected, at src/App/Checkout.php:8.
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
matter is a `dummy()`. A stub that stubs only what matters, and answers the
rest with defaults, ends a stub in `ignoreOthers()`:

```php
beforeEach(function (Filesystem $fs) {
    allow($fs->exists(any()))->toReturn(false);
    allow($fs->isDir(any()))->toReturn(false)->ignoreOthers();
});
```

## How a failure names a double

A failure names a double by the type it stands for and a number, with the
parameter names it was handed under:

```
Expected App\Context double #2 ($second) to be App\Context double #1 ($first)
```

An example numbers its doubles in the order it makes them: a parameter's when
the first closure naming it runs, and one from `mock()` or `dummy()` where it
is called. What a double answered is named by the type its call declares
(`mixed` for none) and by where it came from, and numbered when a failure
first shows it:

```
App\Page double #3 (from App\Book double #1->first())
```

A double keeps its number for the whole example, and every format shows the
same one, whichever worker ran the example. Each example and each scenario
numbers from one.

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

Asked after the act, judged at once from the calls made so far. Nothing needs
declaring before the act.

```php
it('saves the user', function (UserRepository $repo) {
    (new Registration($repo))->register($user);

    expect($repo->save($user))->toHaveBeenCalled();
});
```

Negated, it asks that the call was not made: `expect($repo->save($other))->not()->toHaveBeenCalled()`.

A count follows it, judged from the same calls: `->once()`, `->twice()`,
`->exactly(3)->times()`, `->atLeast(2)->times()` or `->atMost(2)->times()`.

```php
expect($mailer->send(any()))->toHaveBeenCalled()->twice();
```


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
A double matches only itself, never another double of the same type. What a
double answered matches what the same double answers to the same method,
whatever the arguments, so a spy names it by that call again:

```php
(new Reader($book, $printer))->read(); // prints what $book->first() answers
expect($printer->print($book->first()))->toHaveBeenCalled();
```

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

## Using Mocks with `this()`

```php
describe(Notifier::class, function () {
    this('mailer', fn () => mock(Mailer::class));
    this('notifier', fn () => new Notifier($this->mailer));

    it('sends an email', function () {
        expect($this->mailer->send('hello'))->toBeCalled();
        $this->notifier->notify('hello');
    });
});
```

Or using type-hinted injection in `this()`:

```php
describe(Notifier::class, function () {
    this('notifier', fn (Mailer $mailer) => new Notifier($mailer));

    it('sends an email', function () {
        // $this->mailer is available from this() injection
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
| `$repo->save($user)->shouldHaveBeenCalledTimes(2)` | `expect($repo->save($user))->toHaveBeenCalled()->twice()` |
| `Argument::any()`, `Argument::cetera()` | `any()`, `cetera()` |
| `Argument::type('string')` | `type('string')` |
| `Argument::that(fn ($user) => ...)` | `satisfy(fn ($user) => ...)` |

## Supported Types

The mock system handles:

- **Classes**: generates a subclass
- **Interfaces**: generates an implementation
- **Nullable types** (`?Foo`): wraps return with null handling
- **Union types** (`Foo|Bar`): answers with the first type that can have an answer
- **Intersection types** (`Foo&Bar`): creates a combined implementation
- **Enum types**: returns the first case as default
- **Scalar return types** (`string`, `int`, `float`, `bool`, `array`): returns type-appropriate defaults

## Limitations

- Final classes cannot be mocked (PHP reflection limitation)
- Readonly classes cannot be mocked
