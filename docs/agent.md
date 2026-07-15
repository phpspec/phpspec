# Coding Agents (`--format=agent`)

PhpSpec speaks a machine-readable dialect designed for coding agents (Claude
Code, Cursor, or any script that shells out and parses output). Instead of the
ANSI-decorated prose the human formatters produce, `--format=agent` emits a
single JSON document where **the failure itself is the payload** — everything an
agent needs to decide its next move, without re-reading the project.

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

One JSON object is written to stdout, with no ANSI and no prose. It is
`--parallel`-safe: the whole document is emitted once, at the end of the run,
when the parent process holds the complete result.

## The document

A run of three examples — one passing, one failing, one erroring on a missing
class — produces:

```json
{
  "suite": {
    "v": 1, "event": "run_started", "suite": "default",
    "examples": 3, "steps": 0, "seed": null
  },
  "examples": [
    {
      "v": 1,
      "id": "6fd046add251",
      "example": "Basket totals the prices of its products",
      "state": "failing",
      "expected": { "matcher": "toBe", "value": 4000, "negated": false },
      "actual": 3500,
      "message": "Expected 3500 to be 4000",
      "spec": "spec/App/Basket.spec.php:6",
      "rerun": "run spec/App/Basket.spec.php:6"
    },
    {
      "v": 1,
      "id": "66b1647a77b6",
      "example": "Basket applies a coupon",
      "state": "error",
      "exception": {
        "class": "Error",
        "message": "Class \"App\\Coupon\" not found",
        "at": "spec/App/Basket.spec.php:8"
      },
      "spec": "spec/App/Basket.spec.php:8",
      "rerun": "run spec/App/Basket.spec.php:8",
      "offer": { "action": "create_class", "target": "App\\Coupon" }
    }
  ],
  "result": {
    "v": 1, "event": "summary",
    "examples": 3, "steps": 0,
    "passing": 1, "failing": 1, "errors": 1, "pending": 0, "skipped": 0,
    "actionable": 2,
    "duration_ms": 4,
    "offers": [
      { "action": "create_class", "target": "App\\Coupon" },
      { "action": "fake_method", "target": "App\\Basket::total", "value": "4000" }
    ]
  }
}
```

Every object carries `"v"` — the agent-protocol version (currently `1`).

### `suite` — the header

`examples` and `steps` are the totals for the run (`steps` is non-zero only for
Story BDD feature runs). `suite` is the suite name; `seed` is the random-order
seed when one was used, else `null`.

### `examples` — only what needs attention

**Passing examples are omitted.** A green suite of thousands need not spend
tokens on entries an agent will never act on — the summary still counts them.
Each listed entry is one that failed, errored, or is pending/skipped.

| Field | Meaning |
|---|---|
| `id` | A stable identifier for this example — a hash of its full name (described subject + title). It survives edits that move lines or change where a failure fires, so you can ask *"is THIS exact failure still here?"* across runs. Recomputable from `example`. |
| `example` | The full name: the described subject followed by the example title. |
| `state` | `failing`, `error`, `pending`, or `skipped` (`passing` entries are omitted). |
| `spec` | The `file:line` of the failing assertion or the error, project-relative. |
| `rerun` | The exact arguments to re-run **just this one example** — prepend your PhpSpec binary. No full-suite re-run needed to verify one fix. |

**`failing`** entries also carry the expectation:

- `expected` — what the matcher wanted: `matcher` is its name (`toBe`,
  `toThrow`, …), `value` is the target value, and `negated` is `true` when the
  expectation used `not()`.
- `actual` — the value your code actually produced.
- `message` — PhpSpec's rendered failure message.

> **Note on `expected` vs `actual`.** These follow the universal convention:
> `expected.value` is what should have happened, `actual` is what did. (PhpSpec's
> internal naming is the reverse — the formatter un-inverts it for you.)

**`error`** entries carry `exception` (`class`, `message`, `at`) instead. When
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
marker; objects render as `ClassName#id`) so the document never blows up.

### `result` — the summary

The counts (`passing`, `failing`, `errors`, `pending`, `skipped`) are for the
whole run. The one number to branch on is **`actionable`** = failing + errors +
pending. **Zero means there is nothing to do.** `duration_ms` is the run's wall
time; `offers` is the run-wide, de-duplicated list of code PhpSpec can generate.

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

Offers appear in two places: run-wide in `result.offers`, and per-example on the
`offer` field of an `error` entry that maps to a concrete generation.

### Acting on offers

Rather than write the boilerplate itself, an agent can have PhpSpec apply every
pending offer in one non-interactive pass:

```bash
bin/phpspec run --accept-offers            # create missing classes/interfaces/methods/steps
bin/phpspec run --accept-offers --fake     # ...and fill empty methods with their spec'd return values
```

`--accept-offers` runs the suite, applies all offers (no prompts), and exits `0`.
Add `--fake` to also fill empty method bodies with the hardcoded returns their
specs expect (the `fake_method` offers) — a fast way to a first green before you
replace the fakes with real logic.

## Scaffolding with JSON receipts

`describe` and `exemplify` take an `--agent` flag that swaps their prose for a
one-line JSON receipt — so an agent can scaffold without parsing English. No file
content is returned; the agent reads the file itself.

```bash
bin/phpspec describe App/Basket --agent
```
```json
{"v":1,"action":"describe","class":"App\\Basket","spec":"spec/App/Basket.spec.php","created":true}
```

`created` is `false` when the spec already existed (both commands are
idempotent). Combine `describe --agent` with `-e` to also add a method example:

```bash
bin/phpspec describe App/Basket --agent -e checkout
```
```json
{"v":1,"action":"describe","class":"App\\Basket","spec":"spec/App/Basket.spec.php","created":false,"example":{"method":"checkout","added":true}}
```

`exemplify --agent` adds a single method example to a spec (creating the spec
first if needed):

```bash
bin/phpspec exemplify App/Basket checkout --agent
```
```json
{"v":1,"action":"exemplify","class":"App\\Basket","method":"checkout","spec":"spec/App/Basket.spec.php","added":true}
```

`added` is `false` when an example for that method is already present.

## The loop

Putting it together, an agent drives the full cycle through the CLI:

1. **Scaffold** — `describe App/Basket --agent` (and `exemplify … --agent` per
   method) to lay down specs.
2. **Read** — `run --format=agent`; branch on `result.actionable`.
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

Read the single JSON object it prints:

- `result.actionable` is the number to act on. **0 means the suite is green —
  stop.** Otherwise it's `failing + errors + pending`.
- `examples[]` lists only what needs attention (passing examples are omitted).
  Each has a `state`:
  - `failing` — the code ran but behaviour is wrong. Look at `expected.value`
    (what the spec wants), `actual` (what the code produced), and `message`.
    Fix the implementation; do not change the spec to match the code unless the
    spec is genuinely wrong.
  - `error` — an exception was thrown (`exception.class`, `exception.message`).
    If the entry has an `offer`, PhpSpec can generate the missing piece.
  - `pending` — an unimplemented example; implement it.
- To verify a single fix, re-run just that example: take its `rerun` value and
  prepend the binary — e.g. `bin/phpspec run spec/App/Basket.spec.php:6`. Don't
  re-run the whole suite to check one change.
- Track a specific failure across runs by its `id` (stable across edits that
  move lines). A failure is fixed when its `id` no longer appears.

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
