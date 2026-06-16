# PhpSpec

Specification-oriented BDD framework for PHP 8.2+, inspired by RSpec and Jasmine.

**License:** MIT | **PHP:** ^8.2 | **Dependencies:** symfony/console ^7.0

## Installation

```bash
composer require phpspec/phpspec --dev
```

## Philosophy

PhpSpec 9.0 unifies Story BDD and Spec BDD in a single tool — no more juggling phpspec and Behat separately. Write Gherkin feature scenarios to clarify *what* you're building, then drop into specs to drive *how* it's built, class by class. With AI-powered pair programming (`pair`, `next`, `refactor`), the BDD cycle becomes the contract that keeps both human and AI-generated code honest. Read the full [Introduction](docs/introduction.md) for the vision behind 9.0.

## Quick Start

Generate a spec:

```bash
bin/phpspec describe App/Calculator
```

Write your spec in `spec/App/Calculator.spec.php`:

```php
<?php

use App\Calculator;

describe(Calculator::class, function () {
    let('calculator', fn () => new Calculator());

    it('adds two numbers', function () {
        expect($this->calculator->add(2, 3))->toBe(5);
    });

    it('subtracts two numbers', function () {
        expect($this->calculator->subtract(5, 3))->toBe(2);
    });
});
```

Run it:

```bash
bin/phpspec run
```

```
Once you spec, you never go back!

Calculator
  Calculator
    ✓ adds two numbers
    ✓ subtracts two numbers

1 spec
2 examples (2 passes)
Finished in 0.0042 seconds
```

## Features

- **Jasmine/RSpec-style DSL** — `describe`, `context`, `it`, `let`, `expect`
- **30+ built-in matchers** — `toBe`, `toBeTrue`, `toContain`, `toThrow`, `toMatch`, `toHaveProperty`, and more
- **Negation** — `expect($x)->not()->toBe($y)`
- **Custom matchers** — `addMatcher('toBePrime', fn ($n) => isPrime($n), 'Expected %s to be prime')`
- **Built-in mocking** — `mock()` with no external dependencies, automatic type-based double generation
- **Mock verification** — `toBeCalled()`, `toBeCalledWith()`, `toBeCalledTimes()`
- **Stubbing** — `allow($mock->method())->toReturn($value)`
- **Argument matchers** — `any()`, `type('string')`, `callback(fn ($x) => $x > 0)`
- **Type-hinted mock injection** — `it('test', function (Logger $logger) { ... })`
- **Lifecycle hooks** — `beforeEach`, `afterEach`, `beforeAll`, `afterAll`
- **Pending and focused** — `xit()`, `xdescribe()`, `pending()`, `fit()`, `fdescribe()`
- **Story BDD** — Gherkin `.feature` files with `given()`/`when()`/`then()` step definitions
- **Code generation** — specs, classes, interfaces, method stubs, step definitions
- **`--fake` mode** — auto-generates method bodies with hardcoded return values from specs
- **Multiple formatters** — pretty (default), dot, TAP, JUnit XML
- **Code coverage** — text, Clover XML, and HTML reports via xdebug
- **Configuration** — `phpspec.json` with spec paths, autoload, formatter, bootstrap
- **CLI options** — `--stop-on-failure`, `--filter`, `--order=random`, `--seed`, `--profile`, `--bootstrap`, `-v`, `-q`

## CLI Usage

```bash
# Run all specs
bin/phpspec run

# Run a specific file or directory
bin/phpspec run spec/App/Calculator.spec.php

# Run with options
bin/phpspec run --stop-on-failure --format dot
bin/phpspec run --order random --seed 42
bin/phpspec run --filter Calculator
bin/phpspec run --profile

# Generate a spec
bin/phpspec describe App/Calculator
bin/phpspec describe App/Calculator -e add    # with example for "add" method

# Run features (Story BDD)
bin/phpspec run features/

# Code coverage
bin/phpspec run --coverage
bin/phpspec run --coverage-html coverage/
bin/phpspec run --coverage-clover clover.xml
bin/phpspec run --coverage-min 90
```

## Mocking

```php
describe(UserService::class, function () {
    it('returns user name', function (UserRepository $repo) {
        // Stub a return value
        allow($repo->find(1))->toReturn(['name' => 'Alice']);

        $service = new UserService($repo);
        expect($service->getDisplayName(1))->toBe('Alice');
    });

    it('logs activity', function (Logger $logger) {
        // Verify a method is called
        expect($logger->log('hello'))->toBeCalled();
        $logger->log('hello');
    });
});
```

## Story BDD

Write Gherkin features in `features/`:

```gherkin
Feature: Greeting
  Scenario: Say hello
    Given a greeting service
    When I greet "World"
    Then I should see "Hello, World!"
```

Define steps in `features/steps/greeting.steps.php`:

```php
<?php

given('a greeting service', function () {
    $this->greeter = new App\Greeter();
});

when('I greet {string}', function (string $name) {
    $this->result = $this->greeter->greet($name);
});

then('I should see {string}', function (string $expected) {
    expect($this->result)->toBe($expected);
});
```

Run features: `bin/phpspec run features/`

## Documentation

See the [docs/](docs/) directory for detailed documentation:

0. [Introduction](docs/introduction.md) -- Philosophy, BDD cycle, what's new in 9.0
1. [Getting Started](docs/getting-started.md) -- Installation, first spec
2. [Writing Specs](docs/writing-specs.md) -- DSL functions, hooks, pending/focused
3. [Matchers](docs/matchers.md) -- All 30+ matchers
4. [Mocking](docs/mocking.md) -- Test doubles, stubbing, verification
5. [Hooks](docs/hooks.md) -- Lifecycle hooks
6. [Story BDD](docs/story-bdd.md) -- Gherkin features and step definitions
7. [Code Generation](docs/code-generation.md) -- Spec, class, interface, method stub generation
8. [CLI Reference](docs/cli.md) -- Commands and options
9. [Configuration](docs/configuration.md) -- phpspec.json reference
10. [Architecture](docs/architecture.md) -- Internal design
11. [Roadmap](docs/roadmap.md) -- Status and planned features

## License

MIT License. See [LICENSE](LICENSE) for details.
