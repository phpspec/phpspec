# Lifecycle Hooks

Hooks run setup and teardown code around examples, giving you control over shared state and cleanup.

## `beforeEach(Closure $fn)`

Runs before every example in the current context. The closure receives `$this` bound to the spec's `Subject`.

```php
describe(Calculator::class, function () {
    beforeEach(function () {
        $this->calculator = new Calculator();
    });

    it('adds', function () {
        expect($this->calculator->add(2, 3))->toBe(5);
    });

    it('subtracts', function () {
        expect($this->calculator->subtract(5, 3))->toBe(2);
    });
});
```

## `afterEach(Closure $fn)`

Runs after every example in the current context, regardless of pass or fail.

```php
describe('Database', function () {
    afterEach(function () {
        $this->db->rollback();
    });

    it('inserts a record', function () {
        // ...
    });
});
```

## `beforeAll(Closure $fn)`

Runs once when the context is set up, before any examples execute. Useful for expensive setup that can be shared.

```php
describe('ExpensiveService', function () {
    beforeAll(function () {
        $this->service = ExpensiveService::boot();
    });

    it('is ready', function () {
        expect($this->service->isReady())->toBeTrue();
    });
});
```

## `afterAll(Closure $fn)`

Runs once after all examples in the context have finished.

```php
describe('TempFiles', function () {
    afterAll(function () {
        // cleanup temp directory
    });
});
```

## Nested Hook Inheritance

Hooks in parent contexts run for all nested examples. Inner hooks run after outer hooks:

```php
describe('Outer', function () {
    beforeEach(function () {
        $this->log[] = 'outer';
    });

    context('Inner', function () {
        beforeEach(function () {
            $this->log[] = 'inner';
        });

        it('runs both hooks', function () {
            // $this->log is ['outer', 'inner']
            expect($this->log)->toBe(['outer', 'inner']);
        });
    });
});
```

## `let(string $name, Closure $fn)`

While not strictly a hook, `let` is the primary way to set up shared state. The closure runs once when the context is initialized:

```php
describe(UserService::class, function () {
    let('repo', fn () => mock(UserRepository::class));
    let('service', fn () => new UserService($this->repo));

    it('finds users', function () {
        allow($this->repo->find(1))->toReturn(['name' => 'Alice']);
        expect($this->service->find(1))->toBe(['name' => 'Alice']);
    });
});
```

## Type-Hinted Mock Injection

Both `it()` and `let()` closures support type-hinted parameters that are automatically resolved as mocks:

```php
describe(Notifier::class, function () {
    // Mock injected via let()
    let('notifier', fn (Mailer $mailer) => new Notifier($mailer));

    // Mock injected via it()
    it('sends email', function (Mailer $mailer) {
        expect($mailer)->toBeAnInstanceOf(Mailer::class);
    });
});
```

Any parameter with a class or interface type hint that can be mocked will be resolved automatically via `mock()`.

## Hook Execution Order

For a given example, hooks execute in this order:

1. `beforeAll` (once per context, on first run)
2. `beforeEach` (parent contexts first, then current)
3. Example body
4. `afterEach` (current context first, then parent)
5. `afterAll` (once per context, after last example)

## Pending Contexts and Hooks

When a context is marked pending (`xdescribe`, `xcontext`), `beforeAll` and `afterAll` hooks are skipped entirely. Individual pending examples (`xit`, `pending()`) still trigger `beforeEach`/`afterEach` on the context but the example body is not executed.
