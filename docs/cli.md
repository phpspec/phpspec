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

Coaches the single next step in outside-in, feature-first TDD — favouring story tests, grounded in the real suite state.

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

### `generate`

Turns a natural-language instruction into ONE artifact: a Gherkin feature, step
definitions, a spec, or implementation code. The current TDD step is resolved
deterministically from your words (an explicit path, or feature/steps/spec/code
wording), and fully determined artifacts are generated without any model call: a
feature request becomes a Gherkin skeleton, and `generate the steps` writes the
step definitions for the last-touched feature by parsing it. Everything else is
authored by the AI. Each proposal is shown as a diff and written after a `[Y/n]`
confirmation. With no terminal to ask, nothing is written: the change is offered
under an id for [`accept`](#accept) to apply. Requires an AI provider (see
[Configuration](configuration.md#ai)).

```bash
bin/phpspec generate a feature for adding a task    # Gherkin under features/, no model call
bin/phpspec generate the steps                      # steps for the last-touched feature, no model call
bin/phpspec generate a spec for a Coupon that reduces a total
bin/phpspec generate implement Calculator::add to return the sum of its arguments
```

An explicit path in the instruction (`... in features/adding.feature`) always wins,
and the artifact type follows its extension. Every exchange is captured to
`.phpspec/ai/last-request.json`: the resolved step and its reason, the composed
prompt files, and the model's reply, so a surprising result is debuggable at a
glance. Also available in pair mode as `/generate <instruction>`.

### `accept`

Applies an offer PhpSpec made earlier, by its id. A run reports what it could
generate, and `generate` reports what it would write; both wait under an id
rather than changing anything on their own, so the decision is taken by whoever
read them.

```bash
bin/phpspec accept o_7f3a1c2d               # apply exactly that offer
bin/phpspec accept o_7f3a1c2d o_91b0e4aa    # several at once, all or nothing
```

The id is derived from the offer itself, so it is stable while the offer stands.
An unknown id is refused, and so is an offer whose file has changed since it was
made. `--format=agent` returns the receipt as JSON. Offers live in
`.phpspec/offers.json`; the fifty most recent stay on the table.

### `guard`

Turns on the TDD guard and records where this session starts.

```bash
bin/phpspec guard
```

From then on `bin/phpspec run` refuses a change whose new logic no example
reaches, naming the member and showing the lines. `--check --hash=<sha>
--coverage=<file>` asks the same question on demand, after the suite, for CI and
pre-commit hooks. See [Guard](guard.md).

### `describe`

Generates a spec file for a class.

```bash
bin/phpspec describe <class> [options]
```

**Arguments:**
- `class` -- The class path using `/` as namespace separator.

**Options:**
- `-e`, `--exemplify=METHOD` -- Include an example for the specified method.
- `-r`, `--run` -- Run the specs after generating.
- `--agent` -- Emit a machine-readable JSON receipt instead of prose (for coding agents).

**Examples:**

```bash
bin/phpspec describe App/Calculator
bin/phpspec describe App/Console/Command/Run
bin/phpspec describe App/Calculator -e add          # with "add" method example
bin/phpspec describe App/Calculator --agent          # JSON receipt (see Coding Agents)
```

### `exemplify`

Adds an example for a method to a spec file, generating the spec first if needed.

```bash
bin/phpspec exemplify <class> <method> [options]
```

**Arguments:**
- `class` -- The class path using `/` as namespace separator.
- `method` -- The method to add an example for.

**Options:**
- `--agent` -- Emit a machine-readable JSON receipt instead of prose.

```bash
bin/phpspec exemplify App/Calculator add
bin/phpspec exemplify App/Calculator add --agent
```

See [Coding Agents](agent.md) for the `--agent` receipts and
[Code Generation](code-generation.md) for the generated templates.

## Run Options

### Output Format

| Option | Description |
|---|---|
| `-f`, `--format=FORMAT` | Output formatter: `pretty` (default), `dot`, `tap`, `junit`, `html`, `agent`. Repeatable; pair each with `-o` |
| `-o`, `--out=FILE` | Report destination for the corresponding `--format`; `std` means the console |
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

#### HTML Formatter

Outputs a self-contained HTML document with passed/failed examples and a
summary, ready to open in a browser:

```bash
bin/phpspec run --format=html > report.html
```

#### Agent Formatter

Speaks the run to a coding agent in JSON Lines, one event per line as it
happens: no ANSI, no prose, and no waiting for a long suite to end. Each
failing/erroring example arrives as its own line carrying the expectation, what
the code printed, a stable `id`, a line-targeted `rerun` command and any
code-generation `offer`; the closing `summary` line carries the counts, one
`rerun` for every failure at once, the coverage verdict when coverage was
collected, and a run-wide `offers` list:

```bash
bin/phpspec run --format=agent
```

Those lines are the only thing on standard output, whatever happens: the seed
line, the `--profile` table, coverage lines, error text and anything the subject
itself printed all move aside, and a run that dies partway still ends with a
`fatal` line naming what stopped it and a `summary` after it.

See [Coding Agents](agent.md) for the full contract, the `--accept-offers` /
`--fake` apply flow, and a ready-made `CLAUDE.md` snippet.

#### Report Files

Every formatter writes to standard output, so a single format is best saved
with shell redirection as above. To produce report files *in addition to* the
console output — or several formats in one run — repeat `--format`/`-f` and
pair each occurrence with an `--out`/`-o` destination. Outs pair with formats
by position (Behat style), and `std` names the console:

```bash
bin/phpspec run -f html -o report.html                             # pretty on console + HTML file
bin/phpspec run -f pretty -o std -f html -o report.html -f junit -o report.xml
```

A format without a matching `-o` writes to the console; when every format
targets a file, the console falls back to `pretty`. Unknown format names are
rejected with an error rather than silently falling back.

### Filtering and Selection

| Option | Description |
|---|---|
| `--filter=PATTERN` | Only run specs/scenarios whose file path, example title, or scenario title contains PATTERN |
| `--paths-from=FILE` | Read spec/feature paths to run from a file, one per line |
| `--all` | Run all suites -- both specs and features |
| `--story` | Run only features (Story BDD) |
| `--order=ORDER` | Run order: `default` or `random` |
| `--seed=SEED` | Seed for random ordering (for reproducibility) |

**Stopping early.** By default a run continues to the end. These flags halt it at
the first result of a given kind (useful for tight feedback loops and CI):

| Option | Stops on the first... |
|---|---|
| `--stop-on-failure` | failure or error |
| `--stop-on-error` | error |
| `--stop-on-warning` | warning |
| `--stop-on-deprecation` | deprecation |
| `--stop-on-notice` | notice |
| `--stop-on-skipped` | skipped example |
| `--stop-on-problems` | any non-passing result |

```bash
bin/phpspec run --filter Calculator              # Path or title contains "Calculator"
bin/phpspec run --filter "should be good"        # Example/scenario titles matching a phrase
bin/phpspec run --filter "it should be good"     # Leading "it" on the filter is ignored
bin/phpspec run --paths-from specs.txt            # Run the specs listed in specs.txt
bin/phpspec run --stop-on-failure                 # Stop on first failing spec
bin/phpspec run --order random                    # Randomize spec order
bin/phpspec run --order random --seed 42          # Reproducible random order
```

Matching is a case-insensitive substring test. When a spec file's path matches,
every example in it runs; otherwise only the examples whose title matches run.
Feature files behave the same with scenario titles.

`--paths-from` is designed for tools that drive PhpSpec programmatically (such as
mutation testing frameworks): a long list of spec paths passed as arguments can
exceed the operating system's argument size limit, while a file cannot.

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

### Parallel Execution

| Option | Description |
|---|---|
| `--parallel[=N]` | Run specs across N worker processes (defaults to the number of CPU cores) |

```bash
bin/phpspec run --parallel        # one worker per CPU core
bin/phpspec run --parallel=4      # four workers
```

Each worker runs a slice of the spec files in its own process and reports back
via JUnit; the parent merges the results before rendering. Coverage
(`--coverage*`) composes with `--parallel` -- workers collect per-example
coverage and the parent merges it. `--format=agent` is parallel-safe too: the
parent emits each event as a worker reports it, so entries arrive in completion
order rather than file order.

### Code Generation

| Option | Description |
|---|---|
| `--fake` | Auto-generate method bodies with hardcoded return values from spec expectations |
| `--accept-offers` | Apply all pending code-generation offers non-interactively, then exit (for `--format=agent` consumers) |

When specs reference methods that don't exist, PhpSpec can generate method stubs. With `--fake`, it goes further and fills in the method body based on what your specs expect:

```bash
bin/phpspec run --fake
```

`--accept-offers` applies every pending offer (missing classes, interfaces,
methods, feature steps) in one non-interactive pass and exits `0` -- the
scripted counterpart to the interactive "shall I create this?" prompts. Combine
it with `--fake` to also fill empty method bodies:

```bash
bin/phpspec run --accept-offers            # generate all missing code, no prompts
bin/phpspec run --accept-offers --fake     # ...and fill empty methods with spec'd returns
```

See [Coding Agents](agent.md) for the offer format and the full agent workflow.

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
            },
            "executable": [9, 12],
            "methods": {
                "add": {"start": 7, "end": 10}
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
- **`lines`** holds what ran; **`executable`** holds what there was to run, so a
  line nothing reached can be told from a line that was never code (a brace, a
  declaration). A file with no executed lines at all does not appear in
  `sources`.
- **`methods`** gives each method's first and last line, from its `function`
  keyword to the brace that closes it, so a mutation can be located without
  parsing the source again. Methods with no body (interface, abstract) are
  absent. The key is the bare method name, except when two classes in the same
  file declare it, in which case that name is fully qualified
  (`App\Card::pay`) so a lookup finds nothing rather than the wrong range.
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
