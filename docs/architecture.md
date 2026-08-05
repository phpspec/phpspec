# Architecture

## Overview

PhpSpec supports two parallel BDD pipelines: Spec BDD (`.spec.php` files) and Story BDD (`.feature` files).

```
CLI (Symfony Console)
  -> Loader (scans for .spec.php and .feature files)
    -> Suite (collection of Specifications + Features)
      -> Runner (delegates to Suite)
        -> Spec BDD Pipeline:
          -> Specification (loads a single .spec.php file)
            -> Context (describe/context block)
              -> Example (it block)
                -> Expectation -> Matcher -> MatchResult
        -> Story BDD Pipeline:
          -> Feature (loads a single .feature file)
            -> Scenario
              -> Step (given/when/then)
                -> StepMatch -> StepResult
      -> Report (Formatter renders to terminal)
```

## Core Pipeline

### 1. Entry Point -- `bin/phpspec`

Bootstraps autoloading, registers the custom class autoloader, and runs the Symfony Console `Application`.

### 2. Application -- `Console\Application`

Extends Symfony's `Application`. Registers six commands:
- `run` -- load and execute specs
- `describe` -- generate a spec file for a class
- `exemplify` -- add a method example to a spec
- `pair` -- interactive pair-programming REPL with an AI assistant
- `next` -- AI suggests what to describe or specify next
- `refactor` -- AI-powered, behaviour-preserving refactoring

### 3. Run Command -- `Console\Command\Run`

Receives a `Loader`, `Runner`, and `Report`. On execute:
1. Parses CLI options (format, filter, coverage, etc.)
2. `Loader::load()` scans the spec/feature directory
3. `Runner::run()` executes the suite
4. `Report::print()` renders results via the selected formatter

### 4. Loader -- `Loader`

Recursively scans the `spec/` directory (or a given path) for `*.spec.php` and `*.feature` files. Each spec file becomes a `Specification`. Each feature file is parsed by `GherkinParser` into a `Feature`. Returns a `Suite` containing all specifications and features.

### 5. Suite -- `Suite`

Holds an array of `Specification` and `Feature` objects (both implement a common interface). Calls `run()` on each and collects results into a `SuiteResult`. Supports `stopOnFailure` to halt after the first failing spec.

### 6. Specification -- `Specification`

Represents a single `.spec.php` file. On `run()`:
1. Dispatches `SpecificationStarted` event
2. Creates a `Subject` (which `require_once`s the spec file, triggering the DSL functions)
3. Runs each collected `SpecBlock` (Context or Example)
4. Returns a `SpecificationResult`

### 7. Subject -- `Subject`

A dynamic-property object (`#[AllowDynamicProperties]`) that serves as `$this` inside spec closures. Loading the spec file triggers `describe()`/`it()`/`let()` calls, which build the spec tree via the scope stack.

### 8. Context -- `Context`

Represents a `describe` or `context` block. Holds child `SpecBlock`s and lifecycle hooks. On `run()`:
1. Dispatches `ContextStarted`
2. Executes the closure (which registers child blocks)
3. Resolves focus (if any `fit`/`fdescribe`/`fcontext` exists, only focused children run)
4. Runs `beforeAll` hooks, then each child with `beforeEach`/`afterEach` wrapping, then `afterAll` hooks
5. Returns a `ContextResult`

### 9. Example -- `Example`

Represents an `it` block. On `run()`:
1. Dispatches `ExampleStarted`
2. Resolves type-hinted closure parameters as mocks
3. Executes the closure (which calls `expect()` -> matchers)
4. Dispatches `ExampleRunned`
5. Returns an `ExampleResult` with collected `MatchResult`s

## Event Dispatcher

The `Dispatcher` uses a **scope stack** for structure building. DSL functions (`describe`, `it`, `let`) add blocks to `Dispatcher::currentScope()` rather than broadcasting events.

### Events

| Event | When |
|---|---|
| `SuiteStarted` | The suite begins running |
| `SpecificationStarted` | A spec file begins running |
| `SpecificationFinished` | A spec file finishes running |
| `ContextCreated` | `describe()`/`context()` is called |
| `ContextStarted` | A context block begins execution |
| `ContextModified` | `let()` is called |
| `ContextRan` | A context block finishes |
| `ExampleCreated` | `it()`/`its()` is called |
| `ExampleStarted` | An example begins execution |
| `ExampleRunned` | An example finishes |
| `ExampleCompleted` | An example's result has been recorded |
| `ExampleErrored` | An exception is thrown during an example |
| `ExpectationStarted` | `expect()` begins a match |
| `MatchCreated` | A matcher registers a match closure |
| `ExpectationPassed` | A match passes |
| `ExpectationFailed` | A match fails |
| `MethodMocked` | A mock expectation is set |
| `SuiteFinished` | The suite finishes running |

### Subscribers

Subscribers (in `EventDispatcher/Subscriber/`) react to events:

- **`SpecificationSubscriber`** -- Resets the subscribed `Specification` on `SpecificationStarted`, so each spec file builds its tree from a clean slate.
- **`ExampleSubscriber`** -- Collects `MatchCreated` closures, executes them on `ExampleRunned`, and builds the `ExampleResult`. Handles `ExampleErrored`.

### Listeners

- **`LogListener`** -- Logs all `Loggable` events with timestamps. Useful for debugging.

## Story BDD Pipeline

### Feature Loading

The `Loader` detects `.feature` files and parses them using `GherkinParser`, which produces a tree of nodes:

```
FeatureNode
  +-- BackgroundNode (optional shared steps)
  +-- ScenarioNode (regular scenarios)
  +-- ScenarioOutlineNode (parameterized with Examples table)
       +-- StepNode (Given/When/Then/And/But)
            +-- DataTable (optional)
            +-- DocString (optional)
```

### Step Registry

Step definitions are registered via `given()`, `when()`, `then()` functions in `.steps.php` files. The `StepRegistry` maps step patterns to closures using placeholder-based matching (`{string}`, `{int}`, `{word}`, `{*}`).

### Feature Execution

Each `Feature` object:
1. Loads step files from `features/steps/`
2. For each scenario, runs background steps first, then scenario steps
3. Each step is matched against the registry, executed with captured arguments
4. `StepMatchCollector` subscribes to `MatchCreated` events to collect expectations made during steps
5. Results flow through `StepResult` -> `ScenarioResult` -> `FeatureResult`

### Step World

Step closures share a `StepWorld` object (`#[AllowDynamicProperties]`) as `$this`, allowing state to be passed between steps within a scenario.

## Results Tree

Results mirror the spec tree:

```
SuiteResult
  +-- SpecificationResult (per .spec.php file)
  |    +-- ContextResult (per describe/context)
  |         +-- ExampleResult (per it)
  |              +-- MatchResult (per expect().toX())
  |                   +-- Detail (Successful or Failed)
  +-- FeatureResult (per .feature file)
       +-- ScenarioResult (per scenario)
            +-- StepResult (per step)
```

- `MatchResult::passed()` / `MatchResult::failed()` -- factory methods
- `Detail\Failed` carries expected/actual values, message, file, line, surrounding code
- `Counts` traverses the tree to produce summary stats (specs, examples, passes, failures, errors, pending)

## Reporting

The `Report` interface delegates to a `Formatter`. Available formatters:

| Formatter | Description |
|---|---|
| `Pretty` | Full hierarchical output with colors and error details |
| `Dot` | Compact one-character-per-example output |
| `Tap` | TAP (Test Anything Protocol) format |
| `Junit` | JUnit XML for CI integration |
| `Html` | Self-contained HTML report |
| `Agent` | Machine-readable JSON Lines for coding agents, one event per line (see [Coding Agents](agent.md)) |

### Pretty Formatter

Rendering lives in `Report/Formatter/Pretty/PrettyViews.php` -- a class of static
methods, one per node type, each writing colorized output through the Symfony
Console `OutputInterface`:

| Method | Renders |
|---|---|
| `specification()` / `specificationErrors()` | Spec file title + children; its error details |
| `context()` / `contextErrors()` | Context title + indented children; its errors |
| `example()` / `exampleErrors()` | Pass/fail line; error message + code |
| `feature()` / `scenario()` / `step()` | Story BDD feature, scenario, and step lines |
| `matchResult()` | Failure message with expected/actual |
| `surroundingCode()` | Source snippet with the error line highlighted |
| `counts()` | Summary line (X specs, Y examples, Z passes...) |

## Code Coverage

When coverage is enabled (via `--coverage` flags), the `CoverageCollector` uses xdebug to collect line-level coverage data. Three report formats are available:

- `TextReport` -- renders coverage percentages to the terminal
- `HtmlReport` -- generates an HTML report with color-coded source views
- `CloverReport` -- outputs Clover XML for CI tool integration

## Code Generation

The autoloader (`CodeGeneration\Autoloader`) intercepts class-not-found errors and offers interactive generation:

- `ClassGenerator` -- generates PHP class files
- `InterfaceGenerator` -- generates PHP interface files (from mock errors)
- `SpecGenerator` -- generates `.spec.php` files
- `MethodStubGenerator` -- adds method stubs to existing classes/interfaces
- `StepGenerator` -- generates `.steps.php` files for undefined feature steps

The `--fake` flag enhances `MethodStubGenerator` to fill in return values from spec expectations.
