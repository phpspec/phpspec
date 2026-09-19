# Getting Started

## Requirements

- PHP 8.2 or higher
- Composer

## Installation

```bash
composer require phpspec/phpspec --dev
```

## Your First Spec

Generate a spec for a class:

```bash
bin/phpspec describe App/Calculator
```

This creates `spec/App/Calculator.spec.php`:

```php
<?php

use App\Calculator;

describe(Calculator::class, function() {
    let("calculator", fn() => new Calculator());
    it("instantiates", fn() => expect($this->calculator)->toBeAnInstanceOf(Calculator::class));
});
```

## Running Specs

Run all specs (from the `spec/` directory by default):

```bash
bin/phpspec run
```

Run a specific spec file:

```bash
bin/phpspec run spec/App/Calculator.spec.php
```

Run specs from a specific directory:

```bash
bin/phpspec run spec/App/Console
```

## The Spec File Convention

Spec files must end with `.spec.php`. The loader recursively scans the `spec/` directory for all files matching this pattern.

## Auto-Class Generation

When a spec describes a class that doesn't exist, the run says so where the error would have been, and offers the class:

```
Looks like you are trying to spec App\Calculator,
a class that doesn't exist yet.

Would you like me to generate that class for you? [Y/n]
```

Pressing Enter generates the class in `src/` following the namespace structure, and names the file it wrote. Run again and the spec runs against it.

## Story BDD

PhpSpec also supports Gherkin-style feature files. Create `.feature` files in a `features/` directory:

```gherkin
Feature: Calculator
  Scenario: Adding numbers
    Given a calculator
    When I add 2 and 3
    Then the result should be 5
```

Define steps in `features/steps/*.steps.php` and run with:

```bash
bin/phpspec run features/
```

See [Story BDD](story-bdd.md) for full details.

## Code Coverage

Generate a coverage report:

```bash
bin/phpspec run --coverage
```

Or an HTML report:

```bash
bin/phpspec run --coverage-html coverage/
```

Requires the xdebug extension with `xdebug.mode=coverage`.

## Next Steps

- [Writing Specs](writing-specs.md) -- DSL functions, hooks, pending/focused
- [Matchers](matchers.md) -- All 30+ available matchers
- [Mocking](mocking.md) -- Test doubles with `mock()` and `allow()`
- [CLI Reference](cli.md) -- All commands and options
- [Configuration](configuration.md) -- `phpspec.json` reference
