# Story BDD

PhpSpec supports Story BDD alongside Spec BDD. Write acceptance scenarios in Gherkin `.feature` files and implement them with step definitions.

## Feature Files

Create `.feature` files in a `features/` directory:

```gherkin
Feature: Greeting
  As a user
  I want to be greeted
  So that I feel welcome

  Scenario: Say hello
    Given a greeting service
    When I greet "World"
    Then I should see "Hello, World!"
```

## Step Definitions

Define steps in `*.steps.php` files. They are discoverable anywhere inside
the features folder (conventionally `features/steps/`, but a file beside its
feature works too), regardless of which feature path you run. An additional
directory can be searched by setting `steps_path` in the
[configuration](configuration.md#steps_path).

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

### DSL Functions

| Function | Purpose |
|---|---|
| `given(pattern, closure)` | Register a Given step |
| `when(pattern, closure)` | Register a When step |
| `then(pattern, closure)` | Register a Then step |
| `step_and(pattern, closure)` | Register an And step |
| `step_but(pattern, closure)` | Register a But step |

All keywords register into the same step registry -- the keyword is for readability only, matching is pattern-based.

## Step Patterns

Patterns use placeholders that capture values from the step text:

| Placeholder | Matches | PHP type |
|---|---|---|
| `{string}` | Quoted text `"..."` | `string` |
| `{int}` | Integer `\d+` | `int` |
| `{word}` | Single word `\w+` | `string` |
| `{*}` | Anything `.+` | `string` |

```php
given('there are {int} cucumbers', function (int $count) {
    $this->count = $count;
});

when('I eat {int} cucumbers', function (int $eaten) {
    $this->count -= $eaten;
});
```

## Shared World

Each scenario gets a fresh `StepWorld` object. Steps within a scenario share state via `$this`:

```php
given('I store {string} as message', function (string $msg) {
    $this->message = $msg;   // sets $this->message on the StepWorld
});

then('the message should be {string}', function (string $expected) {
    expect($this->message)->toBe($expected);  // reads from same StepWorld
});
```

`StepWorld` uses `#[AllowDynamicProperties]`, so any property can be set dynamically.

## Background

Background steps run before each scenario in a feature:

```gherkin
Feature: Background example
  Background:
    Given a common setup

  Scenario: First
    Then the setup should be available

  Scenario: Second
    Then the setup should be available
```

## Scenario Outline

Expand a scenario template with multiple data rows:

```gherkin
Feature: Cucumbers
  Scenario Outline: Eating
    Given there are <start> cucumbers
    When I eat <eat> cucumbers
    Then I should have <left> cucumbers

    Examples:
      | start | eat | left |
      | 12    | 5   | 7    |
      | 20    | 5   | 15   |
```

Each row in the `Examples:` table generates a concrete scenario with `<placeholder>` values substituted.

## Data Tables

Pass tabular data to a step:

```gherkin
Scenario: User list
  Given the following users:
    | name  | role  |
    | Alice | admin |
    | Bob   | user  |
  Then there should be 2 users
```

```php
use PhpSpec\StoryBDD\DataTable;

given('the following users:', function (DataTable $table) {
    $this->users = $table->toArray();
    // [['name' => 'Alice', 'role' => 'admin'], ['name' => 'Bob', 'role' => 'user']]
});
```

`DataTable` implements `ArrayAccess`, `Iterator`, and `Countable`. Use `$table->asClass(User::class)` to hydrate rows into objects.

## Tags

Add `@tag` annotations to features and scenarios:

```gherkin
@smoke
Feature: Tagged feature
  @wip
  Scenario: Tagged scenario
    Given a step
```

Tags are stored on `FeatureNode::$tags` and `ScenarioNode::$tags`.

## Hooks

Register lifecycle hooks for features and scenarios:

```php
beforeFeature(function () {
    // Runs once before a feature starts
});

beforeScenario(function () {
    // Runs before each scenario (on the StepWorld as $this)
});

beforeStep(function () {
    // Runs before each step
});
```

## Step States

| State | Color | Meaning |
|---|---|---|
| passed | green | Step executed successfully |
| failed | red | Step threw an exception or expectation failed |
| pending | yellow | Step calls `pending()` |
| undefined | blue | No matching step definition found |
| skipped | cyan | Skipped because a prior step failed |

After the first failure in a scenario, all remaining steps are skipped.

## Running Features

```bash
bin/phpspec run features/               # Run all features
bin/phpspec run features/greeting.feature  # Run a specific feature
```

## Step Generation

When running features with undefined steps, PhpSpec offers to generate step definition stubs:

```
3 undefined steps in features/greeting.feature.
Generate step definitions? [Y/n]
```

Generated steps include `pending()` calls so they show as pending until implemented.

## The BDD Cycle

Story BDD and Spec BDD form a full development cycle:

1. Write a `.feature` file describing desired behavior
2. Run `bin/phpspec run features/` -- steps are undefined
3. Generate step definitions, implement them
4. Steps reference classes that don't exist -- PhpSpec offers to generate specs and classes
5. Switch to Spec BDD: write specs, generate classes, implement
6. Return to features -- steps now pass

```
Feature (acceptance) -> Steps -> Specs (unit) -> Classes -> Green
```

## File Conventions

| Path | Purpose |
|---|---|
| `features/*.feature` | Gherkin feature files |
| `features/steps/*.steps.php` | Step definition files |
| `features/**/*.steps.php` | Step files in subdirectories (also scanned) |
