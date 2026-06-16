# Code Generation

PhpSpec generates specs, classes, interfaces, method stubs, and step definitions to accelerate the BDD workflow.

## The `describe` Command

Generate a spec for a class:

```bash
bin/phpspec describe App/Calculator
```

Creates `spec/App/Calculator.spec.php` with a template:

```php
<?php

use App\Calculator;

describe(Calculator::class, function() {
    let("calculator", fn() => new Calculator());
    it("instantiates", fn() => expect($this->calculator)->toBeAnInstanceOf(Calculator::class));
});
```

### With a Method Example

```bash
bin/phpspec describe App/Calculator -e add
```

Generates a spec that includes an example for the `add` method.

### Path Convention

The argument uses `/` as separator: `App/Calculator` becomes `spec/App/Calculator.spec.php`. This maps to the namespace `App\Calculator`.

## Auto-Class Generation

When specs reference a class that doesn't exist, PhpSpec's custom autoloader prompts:

```
Looks like you are trying to spec App\Calculator, a class that doesn't exist yet.
Would you like me to generate that class for you? [y/n]
```

If confirmed, `ClassGenerator` creates the class at `src/App/Calculator.php`:

```php
<?php

namespace App;

class Calculator
{

}
```

## Interface Generation

When a mock references a class that doesn't exist, PhpSpec offers to generate it as an interface:

```
Looks like you are trying to mock App\UserRepository, a class that doesn't exist yet.
Would you like me to generate that interface for you? [y/n]
```

If confirmed, `InterfaceGenerator` creates an interface at `src/App/UserRepository.php`:

```php
<?php

namespace App;

interface UserRepository
{

}
```

### Method Addition to Interfaces

When a mock calls a method that doesn't exist on the interface, PhpSpec offers to add the method:

```
Method "find" does not exist on App\UserRepository.
Would you like me to add it? [y/n]
```

This appends the method signature to the interface.

## Method Stub Generation

When specs use methods that don't exist on a class, `MethodStubGenerator` can add method stubs:

```php
// If Calculator::add() doesn't exist:
expect($calc->add(2, 3))->toBe(5);
```

PhpSpec generates:

```php
public function add()
{
}
```

## `--fake` Mode

With the `--fake` flag, PhpSpec goes beyond empty stubs and generates method bodies with hardcoded return values based on your spec expectations:

```bash
bin/phpspec run --fake
```

Given the spec:

```php
expect($calc->add(2, 3))->toBe(5);
```

PhpSpec generates:

```php
public function add()
{
    return 5;
}
```

The `--fake` flag works by extracting the expected values from matcher results (via `fakeExpression` metadata) and using them as return values. This creates a quick feedback loop: write spec, run with `--fake`, get passing tests immediately, then replace the faked implementation with real logic.

## Step Definition Generation

When running feature files with undefined steps, PhpSpec generates step definition stubs:

```
Undefined step: "a calculator"
Generated step stub in features/steps/calculator.steps.php
```

The generated file contains:

```php
<?php

given('a calculator', function () {
    pending();
});
```

See [Story BDD](story-bdd.md) for more on step definitions.

## The BDD Cycle

PhpSpec's code generation creates a natural BDD workflow:

1. **Write a spec** -- `bin/phpspec describe App/Calculator`
2. **Run specs** -- PhpSpec prompts to generate the missing class
3. **Add examples** -- write `expect($calc->add(2, 3))->toBe(5)`
4. **Run again** -- PhpSpec offers to generate the `add()` method
5. **Implement** -- fill in the real logic (or use `--fake` for a quick pass)
6. **Repeat** -- add more examples, drive the design through specs

The autoloader is registered in `bin/phpspec`:

```php
spl_autoload_register([\PhpSpec\CodeGeneration\Autoloader::class, 'autoload']);
```
