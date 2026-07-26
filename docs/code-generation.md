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

## The `exemplify` Command

Add an example for a single method to a spec (creating the spec first if it
doesn't exist):

```bash
bin/phpspec exemplify App/Calculator add
```

Both `describe -e` and `exemplify` are idempotent -- an example for a method that
is already present is not duplicated.

## Machine-Readable Receipts (`--agent`)

`describe` and `exemplify` accept an `--agent` flag that replaces their prose
with a one-line JSON receipt, so a coding agent can scaffold without parsing
English:

```bash
bin/phpspec describe App/Calculator --agent
```
```json
{"v":1,"action":"describe","class":"App\\Calculator","spec":"spec/App/Calculator.spec.php","created":true}
```

`created` (and `exemplify`'s `added`) report whether anything changed. See
[Coding Agents](agent.md) for the full contract.

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

## Applying Offers Non-Interactively (`--accept-offers`)

Everything above is offered interactively -- PhpSpec asks `[y/n]` before it
writes. `--accept-offers` applies **every** pending offer (missing classes,
interfaces, methods, and feature steps) in one non-interactive pass, then exits
`0`:

```bash
bin/phpspec run --accept-offers            # generate all missing code, no prompts
bin/phpspec run --accept-offers --fake     # ...and fill empty methods with spec'd returns
```

This is the scripted counterpart to the prompts -- built for
`--format=agent` consumers and CI, where an agent reads the run's `offers` and
then has PhpSpec generate them. See [Coding Agents](agent.md) for the offer
format and the full loop.

## Generating From an Instruction (`generate`)

The generators above are deterministic — they emit fixed stubs (`toBe(null)`, empty
method bodies). When a spec is red because an existing method needs *real behaviour*
(not because a class or method is missing), the `generate` command turns a
natural-language instruction into one file edit — a spec example or a piece of
implementation code — authored by the AI, shown as a diff, and written after you confirm:

```bash
bin/phpspec generate an example for App/Calculator add where it sums two numbers
bin/phpspec generate implement Calculator::add to return the sum of its arguments
bin/phpspec generate a scenario in features/user_adds_tasks.feature
```

It requires an AI provider. A spec edit is checked so it never drops an existing example
(specs are grown, not shrunk). When your instruction **names a file path**, that path is
honoured over anything the model picks, and its extension decides the artifact: a
`.feature` path is written as a Gherkin scenario by a deterministic generator (never as a
spec), a `.spec.php` path stays a spec, and a `src/…php` path stays implementation code.

With no path, the **wording routes the request**: feature/scenario/story wording becomes a
Gherkin skeleton under your configured `features_path` (no model call), `the steps` writes
step definitions for the last-touched feature by parsing it (no model call), spec wording
derives the spec path from the class you named, and implement/method wording derives the
source path through your configured layout (PSR-4 prefix included). Every exchange is
captured to `.phpspec/ai/last-request.json` for debugging. In pair mode the same thing is
`/generate <instruction>`, with the diff confirmed through the numbered chooser. See
[Pair Programming & AI](pair.md) and the [CLI Reference](cli.md#generate).

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
