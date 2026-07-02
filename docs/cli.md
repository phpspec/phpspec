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
bin/phpspec run spec/App/Console                    # Run all specs in a directory
bin/phpspec run features/                           # Run all feature files
bin/phpspec run features/greeting.feature           # Run a single feature
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
| `--filter=PATTERN` | Only run spec files whose path contains PATTERN |
| `--paths-from=FILE` | Read spec/feature paths to run from a file, one per line |
| `--stop-on-failure` | Stop execution after the first spec file with a failure or error |
| `--order=ORDER` | Run order: `default` or `random` |
| `--seed=SEED` | Seed for random ordering (for reproducibility) |

```bash
bin/phpspec run --filter Calculator              # Only specs with "Calculator" in path
bin/phpspec run --paths-from specs.txt            # Run the specs listed in specs.txt
bin/phpspec run --stop-on-failure                 # Stop on first failing spec
bin/phpspec run --order random                    # Randomize spec order
bin/phpspec run --order random --seed 42          # Reproducible random order
```

`--paths-from` is designed for tools that drive PhpSpec programmatically (such as
mutation testing frameworks): a long list of spec paths passed as arguments can
exceed the operating system's argument size limit, while a file cannot.

### Bootstrap

| Option | Description |
|---|---|
| `-b`, `--bootstrap=FILE` | PHP file to require before running specs |

```bash
bin/phpspec run --bootstrap tests/bootstrap.php
```

### Configuration File

| Option | Description |
|---|---|
| `-c`, `--config=FILE` | Path to a phpspec configuration file (overrides the working directory lookup) |

```bash
bin/phpspec run --config custom/phpspec.ci.yaml
```

By default PhpSpec looks for `phpspec.yaml`, `phpspec.yml`, `phpspec.json` or
`phpspec.php` in the working directory. With `--config` exactly the given file
is loaded instead (its format is resolved from the extension), and the command
fails if the file does not exist. The option is available on every command and
is forwarded to worker processes in `--parallel` runs.

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
| `--coverage-json=FILE` | Generate JSON coverage report with per-example detail (experimental) |
| `--coverage-src=DIR` | Source directory to scope coverage reports to (overrides config `src_path`) |
| `--coverage-min=PCT` | Fail if coverage is below the threshold (0-100) |

```bash
bin/phpspec run --coverage                       # Text report
bin/phpspec run --coverage-html coverage/        # HTML report
bin/phpspec run --coverage-clover clover.xml     # Clover XML
bin/phpspec run --coverage-json coverage.json    # JSON report (per-example detail)
bin/phpspec run --coverage-min 90                # Fail below 90%
bin/phpspec run --coverage --coverage-src lib    # Scope the report to lib/
```

Coverage requires the xdebug extension with `xdebug.mode=coverage`. Coverage
options can be combined with `--parallel`: workers collect coverage per example
and the parent process merges their results before rendering the reports.

#### JSON Coverage Report (experimental)

The JSON report is designed for tools that need to know **which example covers
which source line** — most notably mutation testing frameworks such as
[Infection](https://infection.github.io/). Its schema is experimental and may
change while the integration is being finalised.

```json
{
    "version": 1,
    "tests": {
        "spec/App/Calculator.spec.php::Calculator > adds two numbers": {
            "time": 0.0021,
            "memory": 524288,
            "spec_file": "spec/App/Calculator.spec.php",
            "spec_checksum": "9b4f1af43ee97a2924b320db64067458"
        }
    },
    "sources": {
        "src/App/Calculator.php": {
            "checksum": "ac4a5f10068b3a275d47b87df2d78d59",
            "lines": {
                "9": ["spec/App/Calculator.spec.php::Calculator > adds two numbers"]
            }
        }
    }
}
```

- **Test identifiers** are `<spec file>::<context titles joined with " > ">`,
  at example granularity. All paths are relative to the project root.
- **`time`** is the example's wall-clock duration in seconds and **`memory`**
  its peak memory usage in bytes, both measured across the example *and* its
  `let`/`beforeEach`/`afterEach` hooks, so setup code counts as covered by the
  example that ran it.
- **`checksum`**/**`spec_checksum`** are MD5 hashes of the file contents,
  letting consumers detect stale coverage data.
- Code executed only in `beforeAll`/`afterAll` hooks is not attributed to any
  example.

## Exit Codes

- `0` -- All examples passed
- `1` -- One or more examples failed or errored

## Dependencies

PhpSpec depends on:
- `php` ^8.2
- `symfony/console` ^7.0

There are no other runtime dependencies. The mocking system is built-in.
