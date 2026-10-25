# CLI Reference

## Commands

### `run`

Loads and executes spec files.

```bash
vendor/bin/phpspec run [files] [options]
```

**Arguments:**
- `files` (optional): Path to a spec file, directory, or feature file. Defaults to `./spec`.

**Examples:**

```bash
vendor/bin/phpspec run                                    # Run all specs in spec/
vendor/bin/phpspec run spec/App/Calculator.spec.php        # Run a single spec
vendor/bin/phpspec run spec/App/Calculator.spec.php:14     # Run the example at line 14
vendor/bin/phpspec run spec/App/Console                    # Run all specs in a directory
vendor/bin/phpspec run features/                           # Run all feature files
vendor/bin/phpspec run features/greeting.feature           # Run a single feature
vendor/bin/phpspec run features/greeting.feature:12        # Run the scenario at line 12
```

### `pair`

Interactive pair programming REPL with optional AI assistant.

```bash
vendor/bin/phpspec pair
vendor/bin/phpspec pair --prompt "write a spec for a Calculator"
```

See [Pair Programming & AI](pair.md) for full documentation.

### `next`

Coaches the single next step in outside-in, feature-first TDD, favouring story tests, grounded in the real suite state.

```bash
vendor/bin/phpspec next
```

See [Pair Programming & AI](pair.md#the-next-command) for full documentation.

### `refactor`

AI-powered, behaviour-preserving refactoring: a plan, then baby steps you
approve one at a time, the whole spec suite checked after each.

```bash
vendor/bin/phpspec refactor                                 # the source file modified last under src_path
vendor/bin/phpspec refactor Calculator                      # the Calculator modified last under src_path
vendor/bin/phpspec refactor "App\Calculator"
vendor/bin/phpspec refactor "App\Calculator::sum"
vendor/bin/phpspec refactor src/App/Calculator.php
vendor/bin/phpspec refactor Calculator --no-interaction     # carry the plan out without asking
vendor/bin/phpspec refactor App/Calculator --format=agent   # the plan as one JSON receipt, nothing written
```

See [Pair Programming & AI](pair.md#the-refactor-command) for full documentation.

### `generate`

Turns a natural-language instruction into ONE artifact: a Gherkin feature, step
definitions, a spec, or implementation code. The command needs the `ai` section
of the config (see [Configuration](configuration.md#ai-assistant)); without it,
it refuses, naming the file to add the section to. The current TDD step is
resolved deterministically from your words (an explicit path, or
feature/steps/spec/code wording). A feature request becomes a Gherkin skeleton
and `generate the steps` appends the step definitions for the last-touched
feature to `features/steps/steps.php` (or the steps file you name) by parsing it,
so those two make no model call; everything else is
authored by the AI. Each proposal is shown as a diff and written after a `[Y/n]`
confirmation. With no terminal to ask, nothing is written: the change is offered
under an id for [`accept`](#accept) to apply.

```bash
vendor/bin/phpspec generate a feature for adding a task    # Gherkin skeleton under features/
vendor/bin/phpspec generate the steps                      # steps for the last-touched feature, parsed from it
vendor/bin/phpspec generate a spec for a Coupon that reduces a total
vendor/bin/phpspec generate implement Calculator::add to return the sum of its arguments
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
vendor/bin/phpspec accept o_7f3a1c2d               # apply exactly that offer
vendor/bin/phpspec accept o_7f3a1c2d o_91b0e4aa    # several at once, all or nothing
```

The id is derived from the offer itself, so it is stable while the offer stands.
An unknown id is refused, and so is an offer whose file has changed since it was
made. `--format=agent` returns the receipt as JSON, naming each offer's target
and the files it wrote. Offers live in `.phpspec/offers.json`; the fifty most
recent stay on the table, each for twenty minutes from when it was last made,
so an offer left by an earlier session, or by an earlier project in the same
directory, is not applied to this one: an older id is refused as expired,
saying when it was made, as an `error` to an agent.

### `guard`

Turns on the TDD guard and records where this session starts.

```bash
vendor/bin/phpspec guard
```

From then on `vendor/bin/phpspec run` refuses a change whose new logic no example
reaches, naming the member and showing the lines. `--check --hash=<sha>
--coverage=<file>` asks the same question on demand, after the suite, for CI and
pre-commit hooks. See [Guard](guard.md).

### `api`

Describes the spec-writing API from the code itself: every DSL function and
matcher with its real signature and summary, the mock and story functions, and
the timing rules nothing can introspect (a value expectation is judged on the
line it is written; `toBeCalled()` is declared before the act and judged over
the whole example, `toHaveBeenCalled()` on its line, after the act; `toThrow()`
runs its callable where it is written).

```bash
vendor/bin/phpspec api                   # as prose
vendor/bin/phpspec api --format=agent    # as one JSON object, for a coding agent
```

Because it is read off the code, it cannot drift from what runs. See
[Coding Agents](agent.md) for the place it takes in an agent's instructions.

### `describe`

Generates a spec file for a class.

```bash
vendor/bin/phpspec describe <class> [options]
```

**Arguments:**
- `class`: The class path using `/` as namespace separator. A name under none of the mapped namespaces is asked about, or put under `default_namespace`; see [Code Generation](code-generation.md#a-class-under-none-of-the-mapped-namespaces).

**Options:**
- `-e`, `--exemplify=METHOD`: Include an example for the specified method.
- `-r`, `--run`: Run the spec just generated, not the whole suite.
- `-f`, `--format=agent`: Emit a machine-readable JSON receipt instead of prose (for coding agents).

**Examples:**

```bash
vendor/bin/phpspec describe App/Calculator
vendor/bin/phpspec describe App/Console/Command/Run
vendor/bin/phpspec describe App/Calculator -e add          # with "add" method example
vendor/bin/phpspec describe App/Calculator --format=agent   # JSON receipt (see Coding Agents)
```

### `exemplify`

Adds an example for a method to a spec file, generating the spec first if needed.

```bash
vendor/bin/phpspec exemplify <class> <method> [options]
```

**Arguments:**
- `class`: The class path using `/` as namespace separator.
- `method`: The method to add an example for.

**Options:**
- `-f`, `--format=agent`: Emit a machine-readable JSON receipt instead of prose.

```bash
vendor/bin/phpspec exemplify App/Calculator add
vendor/bin/phpspec exemplify App/Calculator add --format=agent
```

See [Coding Agents](agent.md) for the JSON receipts and
[Code Generation](code-generation.md) for the generated templates.

## Run Options

### Output Format

| Option | Description |
|---|---|
| `-f`, `--format=FORMAT` | Output formatter: `pretty` (default), `dot`, `tap`, `junit`, `html`, `agent`. Repeatable; pair each with `-o` |
| `-o`, `--out=FILE` | Report destination for the corresponding `--format`; `std` means the console |
| `-v` | Verbose mode: shows the duration of each example and step; `--format=agent` reports the passing entries too |
| `-q` | Quiet mode: suppresses all output, exit code still reflects pass/fail |
| `--profile[=N]` | Show the N slowest examples, each by its path of titles (default: 10) |

#### Pretty Formatter (default)

Hierarchical output with context indentation, check/cross marks, and colored error details:

```
Once you spec, you never go back!

Spec: Calculator
  Calculator
    ✓ adds two numbers
    ✓ subtracts two numbers

1 spec, 2 examples (2 passed)
Finished in 0.0042 seconds
```

An example and a step are reported with the same glyphs: `✓` passed, `✘` failed or errored, `○` pending, `-` skipped, and `?` for a step no definition matches.

#### Dot Formatter

Compact one-character-per-example output:

```
..F.P..E.

9 examples (6 passed, 1 failed, 1 pending, 1 errored)
```

- `.` pass, `F` failure, `P` pending, `E` error, `S` skipped, `U` undefined step

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
vendor/bin/phpspec run --format=junit > results.xml
```

Each spec is a `testsuite` and each example a `testcase` named by its title,
with `classname` naming the file, the describe and the contexts around it
(`HappyHour > HappyHour > at 5pm`, as the pretty sections name an example), so
one title in two contexts reads apart. A feature is
a `testsuite` of scenario suites, each step a `testcase` under its scenario. A
pending or skipped case carries the reason as the `skipped` element's
`message`. Every case and suite carries `time`, in seconds. PHP warnings,
deprecations and notices are left out, as PHPUnit's JUnit log leaves them out:
the console, the HTML report and `--format=agent` carry them.

#### HTML Formatter

Outputs a self-contained HTML document with passed/failed examples and a
summary counted in the console's words, ready to open in a browser. An example
or step that raised a PHP warning, deprecation or notice unfolds to each one,
with the line that raised it, and the header and its group's bar count them:

```bash
vendor/bin/phpspec run --format=html > report.html
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
vendor/bin/phpspec run --format=agent
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
console output (or several formats in one run), repeat `--format`/`-f` and
pair each occurrence with an `--out`/`-o` destination. Outs pair with formats
by position (Behat style), and `std` names the console:

```bash
vendor/bin/phpspec run -f html -o report.html                             # pretty on console + HTML file
vendor/bin/phpspec run -f pretty -o std -f html -o report.html -f junit -o report.xml
```

A format without a matching `-o` writes to the console; when every format
targets a file, the console falls back to `pretty`. Unknown format names are
rejected with an error rather than silently falling back.

### Filtering and Selection

| Option | Description |
|---|---|
| `--filter=PATTERN` | Only run specs/scenarios whose file path, or whose path of titles (`describe > context > example`, `Feature > Scenario`), contains PATTERN |
| `--tags=EXPRESSION` | Only run the scenarios a Cucumber tag expression selects: `@smoke`, `@smoke and not @wip`, `(@a or @b) and not @c`. A feature's tags count for every scenario in it; a spec has no tags and does not run |
| `--paths-from=FILE` | Read spec/feature paths to run from a file, one per line |
| `--all` | Run all suites, both specs and features |
| `--story` | Run only features (Story BDD) |
| `--order=ORDER` | Run order: `default` or `random` |
| `--seed=SEED` | Shuffle by this whole number, as a random run prints it, to reproduce that order; implies `--order random` |

**Stopping early.** By default a run continues to the end. These flags halt it at
the first result of a given kind: nothing after that example or scenario runs,
in its own file or in any later one, and under `--parallel` every worker halts
the same way (useful for tight feedback loops and CI):

| Option | Stops on the first... |
|---|---|
| `--stop-on-failure` | failure or error |
| `--stop-on-error` | error |
| `--stop-on-warning` | warning, from an example or a step |
| `--stop-on-deprecation` | deprecation, from an example or a step |
| `--stop-on-notice` | notice, from an example or a step |
| `--stop-on-pending` | pending example, or pending or undefined step |
| `--stop-on-skipped` | skipped example, or step that skipped itself (not one skipped behind a step that failed or was pending) |
| `--stop-on-problems` | any non-passing result |

```bash
vendor/bin/phpspec run --filter Calculator              # Path or title contains "Calculator"
vendor/bin/phpspec run --filter "should be good"        # Example/scenario titles matching a phrase
vendor/bin/phpspec run --filter "it should be good"     # Leading "it" on the filter is ignored
vendor/bin/phpspec run --filter "when empty"            # A context title selects everything in it
vendor/bin/phpspec run --filter "when empty > starts"   # Titles joined by > narrow to one example
vendor/bin/phpspec run --tags "@smoke and not @wip"      # Scenarios tagged @smoke, unless also @wip
vendor/bin/phpspec run --paths-from specs.txt            # Run the specs listed in specs.txt
vendor/bin/phpspec run --stop-on-failure                 # Stop on first failing spec
vendor/bin/phpspec run --order random                    # Randomize spec order
vendor/bin/phpspec run --order random --seed 42          # Reproducible random order
```

A value an option cannot take stops the run before anything runs, naming the
option and what it takes: an `--order` other than `default` or `random`, a
`--seed` that is not a whole number or that comes with `--order default`, a
`--profile` or `--parallel` count below 1, a `--coverage-min` outside 0 to 100,
and an empty `--filter`, `--out` or coverage path.

Matching is a case-insensitive substring test, not a pattern. When a spec file's
path matches, every example in it runs; otherwise only the examples whose title
matches run. Feature files behave the same with scenario titles.

A run you narrow, with `--filter`, `--tags`, paths or `--paths-from`, fails
(exit code 1) when the whole of it selects nothing, saying what selected nothing,
so an empty selection never passes for green. Paths add up: one that holds
nothing is fine while another holds something. A plain `run` of a project with
nothing to run yet still passes, saying `No specs found.`

`--tags` selects scenarios by their tags with a Cucumber tag expression: `and`,
`or`, `not` and parentheses, `not` binding tightest and `and` before `or`. A tag
on the feature counts for every scenario in it. Only scenarios run under
`--tags`, since a spec example has none; an expression that cannot be read is
refused, naming what was found where. Both `--filter` and `--tags` reach every
`--parallel` worker.

`--paths-from` is designed for tools that drive PhpSpec programmatically (such as
mutation testing frameworks): a long list of spec paths passed as arguments can
exceed the operating system's argument size limit, while a file cannot.

#### Running a single example or scenario by line

Append `:LINE` to a spec or feature path to run just the block at that line:

```bash
vendor/bin/phpspec run spec/App/Calculator.spec.php:14   # The example at line 14
vendor/bin/phpspec run features/checkout.feature:12      # The scenario at line 12
```

Any line inside an example (or scenario) body selects it, an example at the top
of a file included; a line inside a `describe` but outside its examples runs
that whole context. For Gherkin,
targeting a `Scenario Outline:` line runs every row of its examples table,
while targeting a single examples row runs just that expansion. A line that
reaches no example at all stops the run with `No example at <path:LINE>` and
exit code 1: an explicit line is a precise ask, and running nothing is not an
answer to it. The pretty and dot formatters then show the seven lines around
the target, its number in bold, since most misses are off by a line or two.

An `it()` in a loop declares several examples on one line, and its line runs
them all. To run one of them, append `@ID` to the spec path instead, with the
id the agent format reports for it:

```bash
vendor/bin/phpspec run spec/App/Calculator.spec.php@6fd046add251
```

An id that names no example in the file stops the run as a line does.

Several selectors run each addressed block once, whether they name lines of
one file or of many, so the `rerun` command an agent summary carries can be
pasted back as it is:

```bash
vendor/bin/phpspec run spec/App/Calculator.spec.php:14 spec/App/Calculator.spec.php:22
```

Each selector selects, and the run takes them all together: a line that runs
a whole context keeps every example in it, nested contexts included, even when
another selector points inside one of them. A path given whole alongside one
of its lines runs whole.

### Bootstrap

| Option | Description |
|---|---|
| `-b`, `--bootstrap=FILE` | PHP file to require before running specs |

```bash
vendor/bin/phpspec run --bootstrap tests/bootstrap.php
```

### Configuration File

| Option | Description |
|---|---|
| `-c`, `--config=FILE` | Path to a phpspec configuration file (overrides the working directory lookup) |

```bash
vendor/bin/phpspec run --config custom/phpspec.ci.yaml
```

By default PhpSpec looks for `phpspec.yaml`, `phpspec.yml`, `phpspec.json` or
`phpspec.php` in the working directory. With `--config` exactly the given file
is loaded instead (its format is resolved from the extension), and the command
fails if the file does not exist. The option is available on every command, and
every phpspec a command starts reads the same file: the workers of a
`--parallel` run, a `run` in a `pair` session, and the `rerun` an agent is handed.
So does `--bootstrap`, for the workers and the `rerun`. A command PhpSpec
suggests (what `next` proposes, the `describe` a `refactor` refusal points to,
the `guard` that records a baseline) names the binary as you invoked it and
carries the same `--config`.

### Parallel Execution

| Option | Description |
|---|---|
| `--parallel[=N]` | Run specs across N worker processes (defaults to the number of CPU cores) |

```bash
vendor/bin/phpspec run --parallel        # one worker per CPU core
vendor/bin/phpspec run --parallel=4      # four workers
```

Each worker runs a slice of the spec files in its own process, set up as the
run was: the same php settings, and the extensions its command line loaded.
`php -d memory_limit=1G -d extension=pcov vendor/bin/phpspec run --parallel`
gives every worker a 1G ceiling and PCOV. Extensions go over by name, so one
loaded from a path outside `extension_dir` reaches a worker only if
`extension_dir` holds it too. A worker reports each result back whole, over
PhpSpec's own wire (`--format=wire`, one JSON line per spec or feature); the
parent renders them as they arrive, in whichever format
was asked for, with everything a single process would show: a failure's values
and code, an error's site, what an example printed. A worker that dies is
reported as an error on the file it was running, naming its exit code. Coverage
(`--coverage*`) composes with `--parallel`: workers collect per-example
coverage and the parent merges it. A worker whose coverage never reaches the
parent, because it died or the run stopped it, fails the run with the reason
and what the worker said, rather than a smaller number. `--format=agent` is
parallel-safe too: the
parent emits each event as a worker reports it, so entries arrive in completion
order rather than file order.

### Code Generation

| Option | Description |
|---|---|
| `--fake` | Auto-generate method bodies with hardcoded return values from spec expectations |
| `--accept-offers` | Apply all pending code-generation offers non-interactively, then exit (for `--format=agent` consumers) |

When specs reference methods that don't exist, PhpSpec can generate method stubs. With `--fake`, it goes further and fills in the method body based on what your specs expect:

```bash
vendor/bin/phpspec run --fake
```

`--accept-offers` applies every pending offer (missing classes, interfaces,
methods, feature steps) in one non-interactive pass and exits `0`, the
scripted counterpart to the interactive "shall I create this?" prompts. Combine
it with `--fake` to also fill empty method bodies:

```bash
vendor/bin/phpspec run --accept-offers            # generate all missing code, no prompts
vendor/bin/phpspec run --accept-offers --fake     # ...and fill empty methods with spec'd returns
```

See [Coding Agents](agent.md) for the offer format and the full agent workflow.

#### Questions need a terminal

PhpSpec asks its questions only at a terminal. When the standard input is a
pipe or a file, as under `docker compose exec -T`, in a CI step or after
`cmd |`, it asks nothing: an offer is declined and the run says how to take it,
so a run whose input stays open and silent finishes rather than waiting. A
missing terminal is never taken for a yes. To answer from a pipe on purpose,
set `SHELL_INTERACTIVE=1`; `--no-interaction` wins over it. `--accept-offers`
takes every offer with or without a terminal.

```bash
vendor/bin/phpspec run --accept-offers                      # take every offer, no questions
printf 'y\n' | SHELL_INTERACTIVE=1 vendor/bin/phpspec run   # answer the first question with yes
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
vendor/bin/phpspec run --coverage                       # Text report
vendor/bin/phpspec run --coverage-html coverage/        # HTML report
vendor/bin/phpspec run --coverage-clover clover.xml     # Clover XML
vendor/bin/phpspec run --coverage-json coverage.json    # JSON report (per-example detail)
vendor/bin/phpspec run --coverage-min 90                # Fail below 90%
vendor/bin/phpspec run --coverage --coverage-src lib    # Scope the report to lib/
```

Coverage needs an engine: PCOV enabled (`pcov.enabled=1`), or Xdebug in
coverage mode (`xdebug.mode=coverage`). With nothing named in the config, the
run takes PCOV when it is enabled, else Xdebug;
[`coverage_adapter`](configuration.md#coverage_adapter) names one. A run that
cannot collect says which engine it needs and the command that switches it on.
Xdebug records only the source directory and the directories guard reads,
from the moment the run starts collecting; PCOV records its `pcov.directory`.

A line counts as covered when an example or a scenario runs it. That includes
an example's `this` bindings and `beforeEach`/`afterEach` hooks, a scenario's
background and `beforeScenario`/`afterScenario` hooks, and a closure declared
in `describe` that an example calls. Code that runs only in
`beforeAll`/`afterAll`, `beforeFeature`/`afterFeature` or the `describe` body
itself does not count. Every run counts the same way: coverage options can be
combined with `--parallel`, whose workers collect coverage per example and the
parent process merges their results before rendering the reports.

The engines report only the files coverage was recorded for, so a PHP file
under the source directory with none recorded during an example or scenario is
missing from the count rather than counted as uncovered. `--coverage-min`
therefore judges a run only when coverage was recorded for every such file,
except those coverage leaves out (see below); otherwise it names each file it
is missing and fails:

```
Cannot enforce --coverage-min=100: coverage is incomplete.
No coverage was recorded for src/Untested.php during an example or scenario.
```

The text, Clover and HTML reports cover only the files the run loaded.

The engines do not report a property hook that never runs: such a hook counts
neither as covered nor as uncovered, and PCOV reports none at all.

A percentage is cut, never rounded up: 2502 of 2503 lines is 99.9%, not
100.0%. Beside `--coverage-min` it is cut to the places the threshold is
written to, one at least, so it reads below the threshold exactly when it is:

```
Code coverage 66.6% is below the required 66.7%
```

#### Excluding code from coverage

Interfaces never count, and neither do the lines that only declare: a class's
header, a namespaced constant and the end of the file, which the engines report
as run whenever the file loads. Anything else can be left out, so its lines
neither count towards the percentage, `--coverage-min` included, nor appear in
any report, and guard never asks for an example of it.

A class, interface, trait, enum, method or function leaves itself out with an
attribute, or with the docblock tag PHPUnit reads:

```php
use PhpSpec\ExcludeFromCoverage;

#[ExcludeFromCoverage]
final class LegacyMapper
{
}

final class Clock
{
    /** @codeCoverageIgnore */
    public function sleep(int $seconds): void
    {
        sleep($seconds);
    }
}
```

A comment tag leaves out lines:

| Comment | Leaves out |
|---|---|
| `// @codeCoverageIgnore` at the end of a line | that line |
| `// @codeCoverageIgnore` on a line of its own | the line below |
| either, on a line that opens a block (`if (...) {`, `} else {`, `} catch (...) {`, a `case`) | the whole block |
| `// @codeCoverageIgnoreStart` ... `// @codeCoverageIgnoreEnd` | the lines between, or to the end of the file without an End |

```php
if ($handle === false) { // @codeCoverageIgnore
    throw new RuntimeException('Disk full');
}
```

Files, directories and names can also be listed in the config, under
[`coverage_exclude`](configuration.md#coverage_exclude).

#### JSON Coverage Report (experimental)

The JSON report is designed for tools that need to know **which example covers
which source line**, most notably mutation testing frameworks such as
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
  at example granularity, and `<feature file>::<feature title> > <scenario title>`
  for a scenario, an outline's row carrying its values as the run reports it
  (`features/cart.feature::Cart > adds (1, 2)`). All paths are relative to the
  project root; `spec_file` names the feature file for a scenario.
- **`time`** is the example's wall-clock duration in seconds and **`memory`**
  its peak memory usage in bytes, both measured across the example *and* its
  `this`/`beforeEach`/`afterEach` hooks (a scenario's background and scenario
  hooks), so setup code counts as covered by the example that ran it.
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

## Exit Codes

- `0`: All examples passed
- `1`: One or more examples failed or errored

## Dependencies

PhpSpec depends on:
- `php` ^8.3 with `ext-mbstring`, `ext-tokenizer` and `ext-sockets`
- `symfony/console` and `symfony/yaml` ^7.0 || ^8.0
- `cucumber/gherkin` ^39

The mocking system is built in. The AI commands (`pair`, `next`, `generate`,
`refactor`) need the `papi-ai` packages, installed separately; see [Pair Programming & AI](pair.md).
