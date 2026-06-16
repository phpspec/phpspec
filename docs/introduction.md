# Introduction

## Why BDD Matters

There is no real functional difference between Spec BDD and TDD — both
produce working code backed by automated checks. The difference is **the
language**. BDD shifts your focus from *testing* to *behaviour and design*.

Over time the TDD community drifted toward verification: "does this function
return the right value?" BDD aims to shift it back: "does this object behave
the way its collaborators expect?" That small change in vocabulary leads to
better-designed, more loosely-coupled code.

PhpSpec is an xSpec tool. Where xUnit frameworks encourage you to test
after the fact, xSpec asks you to *describe behaviour first* and let the
implementation follow. The spec **is** the design document.

## Story BDD + Spec BDD — Two Levels, One Cycle

BDD operates at two complementary levels:

- **Story BDD** (features) answers *what* — understanding the domain,
  clarifying requirements with stakeholders, and defining acceptance criteria
  in plain language.
- **Spec BDD** (specs) answers *how* — driving the implementation class by
  class, method by method, in the smallest possible steps.

Working only at the story level means working in large steps — you jump from
a failing scenario straight to production code, with nothing guiding the
internal design. Working only at the spec level means you might build
beautifully designed objects that don't solve the actual problem.

Used together, the cycle looks like this:

```
Feature scenario (failing)
  → Step definitions
    → Spec (failing)
      → Class / Method (generated)
        → Implementation (green)
      → Spec (green)
    → Step (green)
  → Scenario (green)
```

Historically PHP developers needed two separate tools to run this cycle —
phpspec for specs and Behat for features. PhpSpec 9.0 unifies both in a
single framework: write `.feature` files with Gherkin syntax, define steps
with `given()` / `when()` / `then()`, and run everything with `bin/phpspec run`.
No context switching, no duplicate configuration, one feedback loop.

## PhpSpec 9.0 — Evolving for the AI Era

AI coding assistants are now part of every developer's workflow. They can
generate classes, methods, and even entire modules in seconds. But speed
without direction is dangerous — an AI will happily produce code that looks
correct but doesn't match the intended behaviour.

This is exactly the problem BDD was designed to solve. A spec is a
machine-readable contract: whether a human or an AI writes the implementation,
the spec validates that the behaviour is correct. BDD becomes *more* important
with AI, not less — specs are the source of truth that prevents hallucinated
code from reaching production.

PhpSpec 9.0 is built for this reality:

- **`pair` mode** — An interactive AI pair programmer that follows the BDD
  cycle. Describe what you want in plain English; the AI generates specs,
  features, step definitions, and source code using the same DSL and patterns
  already in your project.
- **`next` command** — Scans your project and suggests the single most
  impactful next step. It follows the scenario-first workflow: recommend a
  feature before a spec, a spec before an implementation.
- **`refactor` command** — AI-powered, behaviour-preserving refactoring.
  The AI applies a single baby-step refactoring and verifies that specs still
  pass. If they fail, the change is rolled back automatically.
- **`--fake` mode** — Instant feedback with hardcoded return values generated
  from spec expectations. Write the spec, run with `--fake`, see it pass, then
  replace stubs with real logic.
- **Code generation at every level** — Specs, classes, interfaces, methods,
  and step definitions are generated from errors in the BDD cycle, keeping you
  in flow.

The spec remains the contract. The AI is a powerful collaborator, but the
spec decides what "correct" means.

## What's New in 9.0

PhpSpec 9.0 is a ground-up rewrite. The major changes from earlier versions:

- **Jasmine/RSpec-style DSL** — `describe`, `context`, `it`, `let`, `expect`
  replace the `ObjectBehavior` class-based approach
- **Built-in mocking** — `mock()`, `allow()`, and mock expectations with no
  external dependencies (no Prophecy)
- **Unified Story + Spec BDD** — Gherkin `.feature` files and step definitions
  alongside specs, all in one tool
- **30+ matchers** — `toBe`, `toContain`, `toThrow`, `toMatch`,
  `toHaveProperty`, and many more, plus custom matcher support
- **AI-powered pair programming** — `pair`, `next`, and `refactor` commands
  with configurable providers (Anthropic, OpenAI, Google)
- **Parallel execution** — `--parallel[=N]` distributes specs across worker
  processes
- **Extension system** — Custom formatters, matchers, commands, and listeners
  via a formal plugin API
- **HTTP testing** — `visit()`, `get()`, `post()` helpers with response
  matchers for browser-level assertions
- **Code coverage** — Text, HTML, and Clover XML reports via xdebug, with
  minimum threshold enforcement
- **Configuration** — YAML, JSON, or PHP config files with named suites,
  stop conditions, and code generation settings

For installation and your first spec, continue to
[Getting Started](getting-started.md).
