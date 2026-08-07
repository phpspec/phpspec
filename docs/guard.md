# Guard

Guard refuses to let logic through that you never specified.

Not a coverage threshold: it never asks how much of your codebase is tested. It
asks one question, about this session only, and it asks it every time you run:

**Of the code you changed, is there any logic no example reaches?**

Because the judgement is line-level on the change alone, a codebase full of
untested legacy is never implicated, and a refactor passes for free: you changed
covered code, it stayed covered, nothing to answer for.

## Turning it on

```bash
bin/phpspec guard
```

```
Config phpspec.yml updated. Guard is on.
Baseline recorded at 9e0a4ff70b21.
```

Two things happen. Your config gains `guard: {status: active}`, edited in rather
than rewritten, so your comments and ordering survive. And a **baseline** is
recorded under `.phpspec/guard/`: the current commit in a git repository, or a
snapshot of your source files without one. That is the point in time everything
is judged against, so add `.phpspec/` to your `.gitignore`.

Guard reads coverage, so **Xdebug has to be in coverage mode**. Either set
`xdebug.mode=coverage` in your `php.ini`, or run with
`XDEBUG_MODE=coverage bin/phpspec run`. Xdebug's default mode is `develop`,
which collects nothing, and guard will tell you it cannot judge.

Running `bin/phpspec guard` again re-records the baseline. In `mtime` mode that
moves it to your files as they now stand, which forgives everything you have not
committed. In git mode it records the current commit, so uncommitted work stays
in the judgement: commit it, or `allow` it, if you mean guard to let it pass.

### On a team

The baseline is local and gitignored, so a fresh clone has `status: active` in
the committed config and nothing to judge against. Guard says so on the first
run and does not fail it:

```
Guard is on but no baseline is recorded in this checkout: run "bin/phpspec guard" to judge from here.
```

Everyone runs `bin/phpspec guard` once after cloning. CI does not need a
baseline at all: it asks with `--check --hash`, which names the point of
comparison outright. See [Continuous integration](#continuous-integration).

## What a violation looks like

```
$ bin/phpspec run

Guard Violation: the last change violates the TDD cycle.

   12       public function applyCoupon(int $value): int
   13       {
   14 +         if ($value > 100) {
   15 +             return 100;
   16           }
   18 +         return $value;
   19       }

New logic in App\Basket::applyCoupon is untested.
Write an example for App\Basket::applyCoupon, then make it pass.
```

The run exits `1`. Write the example, make it pass, and the same change goes
through untouched.

Write one that reaches part of it and guard says so differently, because you
have done the thing it asked and only some of the change is still uncovered:

```
Part of the new logic in App\Basket::applyCoupon is still unreached.
Extend your examples for App\Basket::applyCoupon to reach lines 14 and 15.
```

`run` is the only gated command. `describe`, `exemplify` and `generate` keep
working, so you can always scaffold your way out of a violation.

## Configuration

```yaml
guard:
  status: active        # active | off      (off unless you say otherwise)
  scope: spec           # spec | story
  detection: git        # git | mtime
  standards: phpspec    # "phpspec", or a path to your own standard
  paths: [src]          # guarded roots
  allow: ["src/Migrations/**"]
```

| Key | Meaning |
|---|---|
| `status` | `active` turns the gate on. Off by default: guard fails runs, and nobody opts into that by omission. |
| `scope` | `spec` (default): any example covering a changed line justifies it. `story` adds the outside-in requirement and is not yet enforced. |
| `detection` | `git` diffs the working tree against the baseline commit. `mtime` compares your files with the recorded snapshot, for projects without git. |
| `standards` | Reserved for the inferential half (an LLM judging the change against a coding standard). Accepted and validated today; it does nothing yet. |
| `paths` | The roots guard has an opinion about. |
| `allow` | Globs dropped from the judgement, for generated or vendored code you keep in the tree. |

**Specs and features are never guarded**, whatever `paths` says. They are the
statement of intent, and demanding that the intent be covered by itself would
invert the whole idea.

An unknown key is named, with the one it was probably meant to be, and the run
stops rather than carrying on ungated:

```
Unknown guard key "detecton". Did you mean "detection"?
```

A setting guard cannot read means guard is off, and off is exactly what a typo
must not be able to do quietly, so `run` refuses until it is fixed. Note that
YAML turns `true` and `false` into booleans: `status: true` is not `active`, and
guard says so in those words.

## What counts as a change

The **working tree** against the baseline, not `HEAD` against the baseline:
during a live cycle nothing is committed yet, and the uncommitted part is
exactly what guard is asked about. In git mode that is `git diff <baseline>`,
which catches staged and unstaged edits together, plus the whole of any file git
has never been told about. Deleted lines are ignored: removing code is never new
logic left unspecified.

## What counts as untested

A changed line is a violation when it is **executable and nothing reached it**.
Xdebug reports three states, not two, so a brace, a blank line, a `use`
statement or a declaration is never a violation, however new it is.

A file the run never loaded at all has no coverage to consult, which is the
shape of a class written and never specified. Guard reads the source instead and
asks which of its lines do something. A method written entirely on one line
counts: it declares and does at once.

## Continuous integration

Guard can also be asked outright, after a run, against a coverage report that
run left behind. This works whether or not the project keeps guard on day to
day:

```bash
bin/phpspec run --coverage-json=cov.json
bin/phpspec guard --check --hash="$BASE" --coverage=cov.json
```

```yaml
- run: |
    BASE=$(git merge-base origin/${{ github.base_ref }} HEAD)
    bin/phpspec run --coverage-json=cov.json
    bin/phpspec guard --check --hash="$BASE" --coverage=cov.json
```

`merge-base` against the protected branch is the robust default: it counts only
what this pull request changed, and survives force-pushes and first-push
all-zero SHAs. `--hash` replaces the recorded baseline; without it the check uses
the baseline as usual.

| Option | Meaning |
|---|---|
| `--check` | Judge now, from a coverage report, and exit `0` or `1`. |
| `--hash=<sha>` | Judge against this commit instead of the recorded baseline. |
| `--coverage=<file>` | The `--coverage-json` report to judge with. Required. |

The report is checked against the code before it is trusted. Every source in it
carries a checksum, and a report describing a file that has changed since is
refused rather than believed:

```
cov.json describes code that has changed since it was written: src/App/Basket.php. Run the suite again with --coverage-json.
```

## When guard cannot judge

Guard needs a baseline and a coverage report. Missing either, it says so in a
sentence naming what to do, and it never passes in silence: a guard that quietly
stops guarding leaves everybody believing they still have one.

Whether that stops you depends on which command asked and why:

| Guard cannot judge because | `bin/phpspec run` | `guard --check` |
|---|---|---|
| the `guard:` section cannot be read | refuses, exit `1` | refuses, exit `1` |
| no baseline is recorded in this checkout | says so, exit `0` | refuses, exit `1` |
| the baseline is unreadable, or its commit is not in this checkout | says so, exit `0` | refuses, exit `1` |
| no coverage was collected, or Xdebug is not in coverage mode | says so, exit `0` | refuses, exit `1` |

A misread config is the same in every checkout and one edit away from fixed, so
refusing costs a moment and can never flap. The rest depend on where the code is
checked out: a clone that has recorded nothing yet, a shallow CI checkout that
does not contain the baseline commit. Failing a developer's run over those would
only teach them to turn guard off, so `run` tells them instead.

`guard --check` refuses all four, because a check that could not check must not
answer "fine", and its inputs are the caller's own: the commit and the report
are named on the command line, so a refusal means one of them is wrong.

## Under `--format=agent`

A violation rides in the summary as data rather than as text to parse, and each
one counts toward `actionable`, because untested logic is work left to do in
exactly that sense:

```json
{"v":2,"event":"summary","actionable":1,"guard":{"held":false,"judged":true,"violations":[
  {"file":"src/App/Basket.php","lines":[14,15,18],
   "member":"App\\Basket::applyCoupon",
   "remedy":"Write an example for App\\Basket::applyCoupon, then make it pass."}
]}}
```

The key is absent when the cycle held. When guard could not judge at all it is
present and says why, so an agent is never left to infer that silence meant a
pass:

```json
{"v":2,"event":"summary","actionable":0,"guard":{"held":true,"judged":false,"violations":[],
  "reason":"Guard is on but no baseline is recorded in this checkout: run \"bin/phpspec guard\" to judge from here."}}
```

## Things worth knowing

- **Guard collects its own coverage.** When it is on, `run` turns coverage on
  for itself, so the verdict always describes the code as it is now. Without
  Xdebug in coverage mode it says it cannot judge and stands down; it never
  fails a run for want of a driver.
- **Guard judges what this run exercised.** Code covered only by another suite,
  or by a run in a separate process, reads as untested to the run in front of
  it. Where that is by design, `allow` those paths.
- **A parallel run is judged once.** Workers see a slice of the specs, so the
  parent judges after merging what all of them collected.
- **The baseline holds file contents in `mtime` mode**, because without them a
  verdict could only be "this file changed", which would implicate every
  untested line in a legacy file the moment you touched one of them.

## See also

- [CLI Reference](cli.md) -- every `run` option, including coverage.
- [Coding Agents](agent.md) -- the agent stream guard reports into.
