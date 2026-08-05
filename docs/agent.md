# Coding Agents (`--format=agent`)

PhpSpec speaks a machine-readable dialect designed for coding agents (Claude
Code, Cursor, or any script that shells out and parses output). Instead of the
ANSI-decorated prose the human formatters produce, `--format=agent` emits **JSON
Lines**: one self-contained event per line, written as it happens, where **the
failure itself is the payload** — everything an agent needs to decide its next
move, without re-reading the project.

The same idea runs through the scaffolding commands: `describe --agent` and
`exemplify --agent` return JSON receipts instead of prose, and `--accept-offers`
applies PhpSpec's code-generation offers non-interactively. Together they let an
agent drive the whole spec → red → green loop through the CLI, never guessing at
output and never hand-editing what PhpSpec can generate.

> **Just want the CLAUDE.md snippet?** Jump to
> [Teaching an agent to use PhpSpec](#teaching-an-agent-to-use-phpspec).

## Running

```bash
bin/phpspec run --format=agent
```

Standard output carries one JSON object per line, with no ANSI and no prose.
Decode a line, act on it, decode the next: on a long suite the first failure
reaches you while the rest is still running.

It is `--parallel`-safe, with one caveat: workers report back through JUnit,
which carries the outcome, the message and a scenario's line, and nothing else.
Entries from a parallel run therefore arrive in completion order and without
`expected`, `actual` or `output`; a failing spec example also arrives without
`spec` and `rerun`, which JUnit has no room for. Run without `--parallel` when
you want the whole of the detail.

## The stream

A run of three examples — one passing, one failing, one erroring on a missing
class — produces four lines:

```json
{"v":2,"event":"run_started","suite":"default","seed":null}
{"v":2,"event":"example","id":"6fd046add251","example":"App\\Basket > totals the prices of its products","state":"failing","expected":{"matcher":"toBe","value":4000,"negated":false},"actual":3500,"message":"Expected 3500 to be 4000","spec":"spec/App/Basket.spec.php:6","rerun":"run spec/App/Basket.spec.php:6"}
{"v":2,"event":"example","id":"66b1647a77b6","example":"App\\Basket > applies a coupon","state":"error","message":"Class \"App\\Coupon\" not found","exception":{"class":"Error","message":"Class \"App\\Coupon\" not found","at":"spec/App/Basket.spec.php:8"},"spec":"spec/App/Basket.spec.php:8","rerun":"run spec/App/Basket.spec.php:8","offer":{"action":"create_class","target":"App\\Coupon"}}
{"v":2,"event":"summary","examples":3,"scenarios":0,"steps":0,"passing":1,"failing":1,"errors":1,"pending":0,"skipped":0,"actionable":2,"duration_ms":4,"offers":[{"action":"create_class","target":"App\\Coupon"},{"action":"fake_method","target":"App\\Basket::total","value":"4000"}]}
```

Every line carries `"v"` — the agent-protocol version (currently `2`; version
`1` was a single JSON object) — and an `"event"` naming its kind:

| `event` | When | Meaning |
|---|---|---|
| `run_started` | always, first | The run began: what it targets and the seed it was shuffled with. |
| `example` | per entry | One example or scenario that needs attention, the moment it is known. |
| `fatal` | when the run died | What stopped it; the counts describe only what ran before it. |
| `summary` | always, last | The totals, and the one number to branch on. |

The order is guaranteed: `run_started` first, `summary` last, whatever happened
in between. A run that never started still emits both.

### `run_started` — the header

`suite` is what the run targets, as the paths were given; `seed` is the
random-order seed when one was used, else `null`. The totals are not here: at
this point nothing has run yet, and the `summary` carries them.

### `example` — only what needs attention

**Passing entries are omitted.** A green suite of thousands need not spend
tokens on entries an agent will never act on — the summary still counts them.
Each listed entry is one that failed, errored, or is pending/skipped.

**One entry is one thing to fix.** A spec run reports examples; a Story BDD run
reports **scenarios**, not steps, because a scenario is what fails, what re-runs,
and what you act on. The steps that did not pass ride inside the entry, so one
broken scenario is a single item naming the step that broke it, never a failure
followed by a train of skipped siblings pointing at the same line. Each row of a
Scenario Outline is its own entry, named by its values
(`Adding > Adding numbers (1, 2)`).

| Field | Meaning |
|---|---|
| `id` | A stable identifier for this example: a hash of its full name. It survives edits that move lines or change where a failure fires, so you can ask *"is THIS exact failure still here?"* across runs. Recomputable from `example`. |
| `example` | The full name, as a path: `App\Basket > totals the prices` for a spec, `Checkout > Paying for a basket` for a scenario. |
| `state` | `failing`, `error`, `pending`, or `skipped` (`passing` entries are omitted). |
| `message` | What went wrong, whatever the state. An `error` entry keeps `exception` too, for the class and the site. |
| `spec` | The `file:line` of the failing assertion or the error, project-relative. For a scenario it is the line its `Scenario:` keyword sits on. Absent when the site is not known. |
| `rerun` | The exact arguments to re-run **just this one example or scenario**: prepend your PhpSpec binary. No full-suite re-run needed to verify one fix. Absent with `spec`. |
| `output` | What the code printed while this entry ran, present only when it printed something. See [Printed output](#printed-output). |
| `steps` | Scenarios only: the steps that did not pass, each `{ title, state, message?, expected?, actual?, at? }`, in the order they were declared. |

**`failing`** entries also carry the expectation:

- `expected` — what the matcher wanted: `matcher` is its name (`toBe`,
  `toThrow`, …), `value` is the target value, and `negated` is `true` when the
  expectation used `not()`.
- `actual` — the value your code actually produced.
- `message` — PhpSpec's rendered failure message.

> **Note on `expected` vs `actual`.** These follow the universal convention:
> `expected.value` is what should have happened, `actual` is what did. (PhpSpec's
> internal naming is the reverse — the formatter un-inverts it for you.)

A **story step** that failed an expectation reports the same pair, on the step
inside `steps` (with `at`, the `file:line` of the expectation in your step file)
and hoisted onto the entry next to `message`, so acting on the entry never means
going a level deeper. A step whose code *threw* has a `message` and no
expectation: there was none to report.

**`error`** entries carry `exception` (`class`, `message`, `at`) as well. When
the error names something PhpSpec can generate — a missing class, interface, or
method — the entry also carries a per-example `offer` (see below). A failing
expectation carries **no** offer: the code exists and its behaviour is simply
wrong, so `state: failing` already says everything.

**Warnings, deprecations and notices.** When an example emits PHP
warnings/deprecations/notices, the entry gains `warnings`, `deprecations` and/or
`notices` arrays of `{ "message", "at" }` — present only when non-empty.
Sometimes the deprecation is the actual clue behind a failure.

Large or object values in `expected`/`actual` are exported compactly (long
strings and arrays are truncated with a `{ "truncated": true, "length": N }`
marker) so one entry can never flood a context window. Objects are named by what
they are, not by which instance they were: an enum as `App\Status::Active`,
anything that can describe itself as `App\Money("12.00 GBP")`, everything else
as `App\Basket`.
Two runs of the same failure therefore report the same value. Floats keep their
fraction, so `90.0` is never reported as the `90` it was compared against.

### Printed output

Anything your code prints while an entry runs — an `echo` left in a spec, a
`var_dump`, the output of a process a step shelled out to and echoed — is
captured and reported as that entry's `output`, rather than landing in the
middle of the stream and breaking it. For a scenario it is everything its steps
printed, in order, **including the steps that passed**: a scenario usually runs
the process in one step and reads the result in another.

`output` is a string, or `{ "truncated": true, "length": N, "value": "…" }` when
there was more than 4000 characters of it. The key is absent when nothing was
printed.

### `summary`

The counts (`passing`, `failing`, `errors`, `pending`, `skipped`) are for the
whole run, in the units the entries are reported in: one per example, one per
scenario. `steps` is a size, not a verdict. The one number to branch on is
**`actionable`** = failing + errors + pending, plus a coverage gate the run
missed and anything that stopped it.
**Zero means there is nothing to do**, and it never disagrees with the exit code.
`duration_ms` is the run's wall time.

| Field | Present when | Meaning |
|---|---|---|
| `rerun` | anything failed with a location | One command that re-runs every failing example at once, so a fix is checked against all of what it was meant to fix. |
| `coverage` | a `--coverage*` option was given | `{ "percent", "required", "met" }`. `required` is `null` without `--coverage-min`, and `met` is then always `true`. A missed gate adds 1 to `actionable`. |
| `offers` | the run found code it can generate | The run-wide, de-duplicated list. Absent when there is nothing to take. |

### `fatal`: when the run could not finish

A run that never started (a missing bootstrap, an unknown format) or that died
partway (a parse error, a class that fails to compile) still answers. It emits a
`fatal` line of `{ "message", "at" }`, keeps whatever it managed to collect, and
counts 1 in `actionable`:

```json
{"v":2,"event":"run_started","suite":"default","seed":null}
{"v":2,"event":"fatal","message":"Class Mute contains 1 abstract method and must therefore be declared abstract or implement the remaining methods (Speaks::speak)","at":"spec/App/Broken.spec.php:8"}
{"v":2,"event":"summary","examples":0,"actionable":1,"…":"…"}
```

### Standard output carries the stream, and nothing else

Under `--format=agent` those lines are the only thing written to standard
output: the randomised-order seed, the `--profile` table, coverage lines, report
notes, error text and anything your own code printed all go elsewhere, and PHP's
own fatal reports are sent to the error stream. Parse stdout a line at a time;
read stderr when you want the human text as well.

## Offers — code PhpSpec can generate for you

An **offer** is a `{ action, target }` pair (with an optional `value`). They are
the same "shall I create this?" prompts the interactive runner shows a human,
turned into flat data:

| `action` | `target` | Meaning |
|---|---|---|
| `create_class` | `App\Coupon` | A referenced class doesn't exist yet. |
| `create_interface` | `App\Repository` | A mocked type doesn't exist yet. |
| `create_method` | `App\Basket::checkout` | A called method doesn't exist on its class/interface. |
| `create_steps` | `features/checkout.feature` | A feature has undefined steps. |
| `fake_method` | `App\Basket::total` | An existing **empty** method that a spec pins to a value; `value` is the return expression `--fake` would insert (e.g. `"4000"`, `"'hello'"`, `"true"`). |

Offers appear in two places: run-wide on the summary's `offers`, and per-example
on the `offer` field of an `error` entry that maps to a concrete generation.

### Acting on offers

**PhpSpec never applies a change because nobody was there to say no.** Every
offer carries an `id` and waits under it, so you accept what you have actually
read, in a second command:

```bash
bin/phpspec accept o_7f3a1c2d               # apply exactly that offer
bin/phpspec accept o_7f3a1c2d o_91b0e4aa    # several at once, all or nothing
```

An id is derived from what the offer would do, so the same offer keeps it
between runs and a new one is visibly new. `accept` refuses an id it has never
seen, and refuses an offer whose file has changed since it was made: at that
point the decision was taken about something else. Nothing is applied unless
every named offer can be.

For the common case of taking everything a run found, the bulk shortcut remains:

```bash
bin/phpspec run --accept-offers            # create missing classes/interfaces/methods/steps
bin/phpspec run --accept-offers --fake     # ...and fill empty methods with their spec'd return values
```

`--accept-offers` runs the suite, applies all of that run's offers (no prompts),
and exits `0`. Add `--fake` to also fill empty method bodies with the hardcoded
returns their specs expect (the `fake_method` offers), a fast way to a first
green before you replace the fakes with real logic.

`generate` proposes rather than writes: its receipt carries an `id` per
proposal with `applied: false`, and `accept` writes exactly the content that was
reported.

## Scaffolding with JSON receipts

`describe` and `exemplify` take an `--agent` flag that swaps their prose for a
one-line JSON receipt — so an agent can scaffold without parsing English. No file
content is returned; the agent reads the file itself.

```bash
bin/phpspec describe App/Basket --agent
```
```json
{"v":2,"action":"describe","class":"App\\Basket","spec":"spec/App/Basket.spec.php","created":true}
```

`created` is `false` when the spec already existed (both commands are
idempotent). Combine `describe --agent` with `-e` to also add a method example:

```bash
bin/phpspec describe App/Basket --agent -e checkout
```
```json
{"v":2,"action":"describe","class":"App\\Basket","spec":"spec/App/Basket.spec.php","created":false,"example":{"method":"checkout","added":true}}
```

`exemplify --agent` adds a single method example to a spec (creating the spec
first if needed):

```bash
bin/phpspec exemplify App/Basket checkout --agent
```
```json
{"v":2,"action":"exemplify","class":"App\\Basket","method":"checkout","spec":"spec/App/Basket.spec.php","added":true}
```

`added` is `false` when an example for that method is already present.

## The loop

Putting it together, an agent drives the full cycle through the CLI:

1. **Scaffold** — `describe App/Basket --agent` (and `exemplify … --agent` per
   method) to lay down specs.
2. **Read** — `run --format=agent`; act on each `example` line as it arrives,
   and branch on the `summary` line's `actionable`.
3. **Generate** — for `create_*` offers, either write the code or run
   `run --accept-offers`; for `fake_method` offers, `run --accept-offers --fake`
   to get a first green.
4. **Fix & verify** — implement real behaviour for `failing` entries, then
   `run <entry.rerun>` to check just that one example. Track it by `id` across
   runs.
5. **Repeat** until `actionable` is `0` (exit code `0`).

## Teaching an agent to use PhpSpec

Drop something like this into your `CLAUDE.md` (or `.cursorrules`, or any
agent-instruction file) so the agent uses PhpSpec effectively:

```markdown
## Running specs with PhpSpec

Always run PhpSpec with the machine-readable formatter and parse the JSON:

    bin/phpspec run --format=agent

It prints JSON Lines: one JSON object per line. Decode each line and branch on
its `event`:

- `summary` is the last line. Its `actionable` is the number to act on. **0 means
  there is nothing left, so stop.** It counts `failing + errors + pending`, plus
  a missed `--coverage-min` gate and anything that stopped the run.
- A `fatal` line means the run could not finish. Read it before anything else:
  the counts describe only what ran before it.
- `example` lines are what needs attention (passing examples are not reported).
  Each has a `state`:
  - `failing` — the code ran but behaviour is wrong. Look at `expected.value`
    (what the spec wants), `actual` (what the code produced), and `message`.
    Fix the implementation; do not change the spec to match the code unless the
    spec is genuinely wrong.
  - `error`: an exception was thrown. `message` says what, `exception` adds the
    class and the site. If the entry has an `offer`, PhpSpec can generate the
    missing piece.
  - `pending` — an unimplemented example; implement it.
- `output` on an entry is what the code printed while it ran: read it, it is
  often the whole diagnosis for a scenario that drove a process of its own.
- To verify a single fix, re-run just that example: take its `rerun` value and
  prepend the binary — e.g. `bin/phpspec run spec/App/Basket.spec.php:6`. Don't
  re-run the whole suite to check one change. The `summary`'s `rerun` does the
  same for every failure at once.
- Track a specific failure across runs by its `id` (stable across edits that
  move lines). A failure is fixed when its `id` no longer appears. A failing
  Story BDD scenario has an `id` and a `rerun` of its own, just like an example.

## Generating code

Prefer letting PhpSpec generate boilerplate over writing it by hand:

- Scaffold a spec: `bin/phpspec describe App/Basket --agent`
- Add a method example: `bin/phpspec exemplify App/Basket checkout --agent`
- Apply all pending offers (missing classes/interfaces/methods/steps):
  `bin/phpspec run --accept-offers`
- Also fill empty methods with their spec'd return values (fast first green):
  `bin/phpspec run --accept-offers --fake`

`--fake` produces hardcoded returns to reach green quickly — always replace them
with real logic before considering the work done. Offers are suggestions, not
commands: skip any that don't fit the design.

## Workflow

1. `describe`/`exemplify` to lay down specs (red).
2. `run --format=agent`; if `actionable > 0`, act on the entries.
3. Use `--accept-offers` for missing code; implement real behaviour for
   `failing` entries.
4. Re-run each fixed example via its `rerun`; repeat until `actionable` is 0.
```

## Exit codes

Unchanged from every other formatter: `0` when all examples pass, `1` when any
fail or error. `--accept-offers` exits `0` after applying offers.

## See also

- [CLI Reference](cli.md) — every `run` option, and the human formatters.
- [Code Generation](code-generation.md) — how the offers are produced.
- [Story BDD](story-bdd.md) — feature runs (`steps`) and step generation.
