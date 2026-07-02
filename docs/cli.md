# CLI Reference

## Commands

### `run`

Loads and executes spec files.

```bash
bin/phpspec run [files] [options]
```

**Arguments:**
- `files` (optional) -- Path to a spec file, directory, or feature file. Defaults to `./spec`.

**Examples:**

```bash
bin/phpspec run                                    # Run all specs in spec/
bin/phpspec run spec/App/Calculator.spec.php        # Run a single spec
bin/phpspec run spec/App/Calculator.spec.php:14     # Run the example at line 14
bin/phpspec run spec/App/Console                    # Run all specs in a directory
bin/phpspec run features/                           # Run all feature files
bin/phpspec run features/greeting.feature           # Run a single feature
bin/phpspec run features/greeting.feature:12        # Run the scenario at line 12
```

### `pair`

Interactive pair programming REPL with optional AI assistant.

```bash
bin/phpspec pair
bin/phpspec pair --prompt "write a spec for a Calculator"
```

See [Pair Programming & AI](pair.md) for full documentation.

### `next`

AI suggests what to describe or specify next.

```bash
bin/phpspec next
```

See [Pair Programming & AI](pair.md#the-next-command) for full documentation.

### `refactor`

AI-powered, behaviour-preserving refactoring.

```bash
bin/phpspec refactor "App\Calculator"
bin/phpspec refactor "App\Calculator::sum"
```

See [Pair Programming & AI](pair.md#the-refactor-command) for full documentation.

### `describe`

Generates a spec file for a class.

```bash
bin/phpspec describe <class> [options]
```

**Arguments:**
- `class` -- The class path using `/` as namespace separator.

**Options:**
- `-e`, `--example` -- Include an example for the specified method.

**Examples:**

```bash
bin/phpspec describe App/Calculator
bin/phpspec describe App/Console/Command/Run
bin/phpspec describe App/Calculator -e add          # with "add" method example
```

## Run Options

### Output Format

| Option | Description |
|---|---|
| `-f`, `--format=FORMAT` | Output formatter: `pretty` (default), `dot`, `tap`, `junit` |
| `-v` | Verbose mode -- shows per-example duration |
| `-q` | Quiet mode -- suppresses all output, exit code still reflects pass/fail |
| `--profile[=N]` | Show the N slowest examples (default: 10) |

#### Pretty Formatter (default)

Hierarchical output with context indentation, check/cross marks, and colored error details:

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

#### Dot Formatter

Compact one-character-per-example output:

```
..F.P..E.

9 examples (6 passes, 1 failure, 1 pending, 1 error)
```

- `.` pass, `F` failure, `P` pending, `E` error

#### TAP Formatter

Test Anything Protocol output:

```
TAP version 13
1..5
ok 1 - adds two numbers
ok 2 - subtracts two numbers
not ok 3 - divides by zero
```

#### JUnit XML Formatter

Outputs JUnit XML for CI integration:

```bash
bin/phpspec run --format=junit > results.xml
```

### Filtering and Selection

| Option | Description |
|---|---|
| `--filter=PATTERN` | Only run specs/scenarios whose file path, example title, or scenario title contains PATTERN |
| `--stop-on-failure` | Stop execution after the first spec file with a failure or error |
| `--order=ORDER` | Run order: `default` or `random` |
| `--seed=SEED` | Seed for random ordering (for reproducibility) |

```bash
bin/phpspec run --filter Calculator              # Path or title contains "Calculator"
bin/phpspec run --filter "should be good"        # Example/scenario titles matching a phrase
bin/phpspec run --filter "it should be good"     # Leading "it" on the filter is ignored
bin/phpspec run --stop-on-failure                 # Stop on first failing spec
bin/phpspec run --order random                    # Randomize spec order
bin/phpspec run --order random --seed 42          # Reproducible random order
```

Matching is a case-insensitive substring test. When a spec file's path matches,
every example in it runs; otherwise only the examples whose title matches run.
Feature files behave the same with scenario titles.

#### Running a single example or scenario by line

Append `:LINE` to a spec or feature path to run just the block at that line:

```bash
bin/phpspec run spec/App/Calculator.spec.php:14   # The example at line 14
bin/phpspec run features/checkout.feature:12      # The scenario at line 12
```

Any line inside an example (or scenario) body selects it; a line inside a
`describe` but outside its examples runs that whole context. For Gherkin,
targeting a `Scenario Outline:` line runs every row of its examples table,
while targeting a single examples row runs just that expansion.

### Bootstrap

| Option | Description |
|---|---|
| `-b`, `--bootstrap=FILE` | PHP file to require before running specs |

```bash
bin/phpspec run --bootstrap tests/bootstrap.php
```

### Code Generation

| Option | Description |
|---|---|
| `--fake` | Auto-generate method bodies with hardcoded return values from spec expectations |

When specs reference methods that don't exist, PhpSpec can generate method stubs. With `--fake`, it goes further and fills in the method body based on what your specs expect:

```bash
bin/phpspec run --fake
```

### Code Coverage

| Option | Description |
|---|---|
| `--coverage` | Show text coverage report in terminal |
| `--coverage-html=DIR` | Generate HTML coverage report in the specified directory |
| `--coverage-clover=FILE` | Generate Clover XML coverage report |
| `--coverage-min=PCT` | Fail if coverage is below the threshold (0-100) |

```bash
bin/phpspec run --coverage                       # Text report
bin/phpspec run --coverage-html coverage/        # HTML report
bin/phpspec run --coverage-clover clover.xml     # Clover XML
bin/phpspec run --coverage-min 90                # Fail below 90%
```

Coverage requires the xdebug extension with `xdebug.mode=coverage`.

## Exit Codes

- `0` -- All examples passed
- `1` -- One or more examples failed or errored

## Dependencies

PhpSpec depends on:
- `php` ^8.2
- `symfony/console` ^7.0

There are no other runtime dependencies. The mocking system is built-in.
