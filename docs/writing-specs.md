# Writing Specs

PhpSpec uses a Jasmine/RSpec-style DSL built on global functions: `describe`, `context`, `it`, `its`, `let`, and `expect`.

## `describe(string $title, Closure $examples)`

Groups related specs. Typically wraps an entire class under specification. The title is usually the fully-qualified class name.

```php
describe(Calculator::class, function() {
    // examples go here
});
```

## `context(string $title, Closure $examples)`

Alias for `describe`. Used to create nested groups that represent specific scenarios or states.

```php
describe(Calculator::class, function() {
    context("when adding positive numbers", function() {
        it("returns their sum", fn() => expect(1 + 2)->toBe(3));
    });

    context("when dividing by zero", function() {
        // ...
    });
});
```

Contexts can be nested to any depth, and the output will indent accordingly.

## `it(string $title, Closure $example)` / `its(...)`

Defines a single example (test case). The title should read like a sentence -- "it does something".

```php
it("returns the sum of two numbers", function() {
    $calc = new Calculator();
    expect($calc->add(2, 3))->toBe(5);
});
```

`its()` is an alias for `it()`, useful for readability:

```php
its("name is John", fn() => expect($this->user->name)->toBe("John"));
```

### Type-Hinted Mock Injection

`it()` closures support type-hinted parameters that are automatically resolved as mocks:

```php
it('sends notifications', function (Mailer $mailer) {
    // $mailer is a mock of Mailer, injected automatically
    expect($mailer)->toBeAnInstanceOf(Mailer::class);
});
```

## `let(string $property, Closure $setter)`

Defines a lazily-evaluated property on the spec's **World** (the `$this` context shared across examples within a describe/context block).

```php
describe(Calculator::class, function() {
    let("calculator", fn() => new Calculator());

    it("instantiates", fn() =>
        expect($this->calculator)->toBeAnInstanceOf(Calculator::class));
});
```

The closure is called once when the context is set up, and the result is assigned to `$this->calculator`. This works because the `Subject` object (which is the `$this` inside spec closures) uses `#[AllowDynamicProperties]`.

`let()` also supports type-hinted mock injection:

```php
let("generator", fn () => mock(SpecGenerator::class));
let("service", fn (UserRepository $repo) => new UserService($repo));
```

## `expect(mixed $subject)`

Creates an `Expectation` object to make assertions. See [Matchers](matchers.md) for all available matchers.

```php
expect($value)->toBe(42);
expect($user)->toBeAnInstanceOf(User::class);
expect($list)->toHaveCount(3);
```

When the subject is a mock double (implements `MatchableDouble`), `expect()` returns a `Mock\Expectation` instead, which provides mock-specific matchers like `toBeCalled()`.

## Stubbing with `allow()`

Use `allow()` to stub mock method return values:

```php
it('returns user data', function (UserRepository $repo) {
    allow($repo->find(1))->toReturn(['name' => 'Alice']);

    expect($repo->find(1))->toBe(['name' => 'Alice']);
});
```

See [Mocking](mocking.md) for full details on stubbing and verification.

## Lifecycle Hooks

### `beforeEach` / `afterEach`

Run before/after every example in the current context:

```php
describe(Calculator::class, function () {
    beforeEach(function () {
        $this->calculator = new Calculator();
    });

    it('adds', fn () => expect($this->calculator->add(2, 3))->toBe(5));
});
```

### `beforeAll` / `afterAll`

Run once per context, before/after all examples:

```php
beforeAll(function () {
    $this->service = ExpensiveService::boot();
});
```

Hooks inherit to nested contexts. See [Hooks](hooks.md) for details on execution order and nesting.

## Pending and Skipped

Mark examples or contexts as pending (skipped):

```php
xit("not yet implemented", fn() => /* ... */);
xdescribe("pending feature", function() { /* ... */ });
xcontext("pending context", function() { /* ... */ });
```

Or mark pending inside an example:

```php
it("does something", function() {
    pending();
    // code below is not executed
});
```

Pending examples appear in output with a `P` marker and are counted separately.

## Focused Examples

Run only specific examples or contexts:

```php
fit("only this runs", fn() => expect(true)->toBeTrue());
fdescribe("only this group runs", function() { /* ... */ });
fcontext("only this context runs", function() { /* ... */ });
```

When any focused block exists in a context, only focused blocks are executed. This is resolved per-context, not globally.

## How `$this` Works

Each spec file is loaded via a `Subject` object. The `Subject` class has `#[AllowDynamicProperties]`, so `let()` calls dynamically set properties on it. Inside `it()` closures, `$this` refers to this shared Subject, giving all examples access to the `let`-defined properties.

## Nesting Structure

```
Specification (file)
  +-- Context (describe/context block)
       |-- Example (it block)
       |-- Example (it block)
       +-- Context (nested context)
            +-- Example (it block)
```

Each `describe`/`context` creates a `Context` object. Each `it` creates an `Example` object. Both implement `SpecBlock` and are composed into a tree via the scope stack.
