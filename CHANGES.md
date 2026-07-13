# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/)
and this project adheres to [Semantic Versioning](http://semver.org/spec/v2.0.0.html).

## [9.0.0-beta.7](https://github.com/phpspec/phpspec/compare/9.0.0-beta.6...9.0.0-beta.7)

### Added
 - Pair mode driver/navigator roles: `/swap` hands the keyboard between you and the AI. While you drive, the AI navigates and never writes files on its own (its write tools are withheld, not just discouraged); after `/swap` it drives, writing one artifact per turn before handing back. Role contracts live in reviewed prompt artifacts
 - Configure the AI's per-call output-token ceiling with `ai.max_tokens` in your phpspec config (default 16384), so a slow reasoning model is not cut off mid-answer
 - Pair mode's AI can inspect a class, interface or trait by name (`inspect_symbol`): whether it exists, where its file is, and its real public method signatures from Reflection — so it checks a type's actual API before writing against it, and a symbol that does not exist yet is reported cleanly instead of as a missing file

### Changed
 - Pair mode opens with an observation drawn from your suite's state (the failure to start on, the nearest pending example, or a clean slate) instead of a static command menu; the greeting adapts to whether an AI provider is configured
 - Pair mode's AI now reads the suite's real red/green state each turn — the failing and pending examples with their error messages — so it reasons from what the suite actually did rather than guessing, and stays grounded in the same reality you see
 - Pair mode's AI runs specs through the structured runner (`run_specs` now reports red/green with the failing and pending examples, not raw stdout) and auto-verifies its own change: after the driver writes its one artifact, the suite runs and the fresh state is fed straight back, so it never has to guess whether the change worked
 - Pair mode's driving AI works as a conversation — clarify the intent, state a one-line plan, confirm, make the one change, present the diff and result. The plan is shown at the go-ahead prompt so you agree to something concrete, and declining steers it to re-plan rather than repeat the same write
 - Pair mode grows specs instead of overwriting them: the AI starts a spec with `describe` (an empty skeleton, idempotent) and adds behaviour one example at a time with `add_example`. There is no whole-file spec write; a raw write to a spec path that would drop existing examples, or that uses phpspec 8 ObjectBehavior syntax, is rejected
 - Pair mode keeps long AI sessions fast and focused: superseded suite-state grounding is dropped each turn, stale tool output is trimmed, and once the history grows large the oldest turns are folded into a compact "earlier in this session" summary — always keeping the system prompt, the recent turns, and the current state
 - Pair mode `next` reads the real suite state and coaches the next step (run a red spec to drive out the class, fill the nearest pending example) instead of guessing
 - Show a real diff for generated methods and exemplified specs
 - Include an add example AI tool
 - Give the next command enough output tokens for reasoning models to answer

### Fixed
 - `next` no longer loops suggesting you describe a spec that already exists; when the spec is there it coaches you to run it and drive out the missing class
 - Code generation no longer offers to create a class, interface or spec whose file already exists: a `Class "X" not found` reported at runtime (a PSR-4/autoload mismatch, not a missing file) is now checked against the file the generator would actually write, using the configured PSR-4 prefix consistently for both the check and the write

## [9.0.0-beta.6](https://github.com/phpspec/phpspec/compare/9.0.0-beta.5...9.0.0-beta.6)

### Fixed
 - Pair mode picks up code generated earlier in the session by running each `run` in a fresh subprocess
 - Generated `And`/`But` step definitions use the keyword of the step they follow (`when()`/`then()`), not always `given()`

## [9.0.0-beta.5](https://github.com/phpspec/phpspec/compare/9.0.0-beta.4...9.0.0-beta.5)

### Added
 - Pair-mode code-generation prompts (create class/method/interface, generate steps, run specs) now use the same numbered chooser as the rest of pair mode
 - Fix: running specs more than once in a single pair session no longer loses output or crashes on specs that declare a top-level class/interface

## [9.0.0-beta.4](https://github.com/phpspec/phpspec/compare/9.0.0-beta.3...9.0.0-beta.4)

### Added
- Improved pair mode capture of user input

## [9.0.0-beta.3](https://github.com/phpspec/phpspec/compare/9.0.0-beta...9.0.0-beta.3)

### Added
- After hooks
- Ability to run single scenarios given a line (some_feature.feature:42)
- Native `--coverage-json` report (per-example line coverage, per-test timing/memory, source/spec checksums), for Infection
- `--coverage-src=DIR` to scope coverage without a config file
- `--paths-from=FILE` (ARG_MAX-safe spec list)
- Re-add `--config=FILE` (explicit path; not CWD)

## 9.0.0-beta

### Breaking Changes
- Complete rewrite with Jasmine/RSpec-style syntax
- Removed Prophecy dependency — built-in mock system
- Removed ObjectBehavior base class — closures everywhere
- Removed dependency on symfony/event-dispatcher, symfony/process, symfony/finder, doctrine/instantiator, sebastian/exporter, phpspec/php-diff
- Spec files now use `.spec.php` suffix

### Added
- `describe()`, `context()`, `it()`, `let()` DSL (global functions)
- `expect($value)->toBe()` assertion style
- Built-in mock system with type-hinted parameter injection
- `allow($mock->method())->toReturn(val)` stubbing
- `toBeCalled()`, `toBeCalledWith()`, `toBeCalledTimes()` verification
- Argument matchers: `any()`, `type()`, `callback()`, `noArgs()`, `cetera()`
- Fluent call count: `->once()`, `->twice()`, `->never()`, `->exactly(N)->times()`
- Negation via `expect($x)->not()->toBe($y)`
- Custom matchers via `addMatcher()`
- Pending/focused/skipped: `xit()`, `xdescribe()`, `fit()`, `fdescribe()`
- Story BDD with Gherkin `.feature` files (built-in, no Behat)
- `given()`, `when()`, `then()` step definitions
- Scenario Outline with Examples tables
- DataTable support with `asClass()` hydration
- Parallel test execution via Fibers
- Code generation: specs, classes, interfaces, method stubs
- `--fake` flag for generating method bodies from spec expectations
- `--story` flag for running only feature scenarios
- AI-assisted pair programming (`pair`, `next`, `refactor` commands)
- Formatters: Pretty, Dot, TAP, JUnit XML
- `--stop-on-failure`, `--stop-on-error`, `--filter`, `--order=random`, `--profile`
- Per-example timing and suite duration reporting
- 90%+ code coverage enforcement

## [8.2.0](https://github.com/phpspec/phpspec/compare/8.1.0...8.2.0)
### Added
- PHP 8.5 compatibility

## [8.1.0](https://github.com/phpspec/phpspec/compare/8.0.0...8.1.0)
### Added
- Symfony 8 compatibility

## [8.0.0](https://github.com/phpspec/phpspec/compare/7.6.0...8.0.0)
### Added
- PHP 8.4 compatibility [@jrfnl](https://github.com/jrfnl) [@andypost](https://github.com/andypost)
