# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/)
and this project adheres to [Semantic Versioning](http://semver.org/spec/v2.0.0.html).

## [9.0.0-beta.9](https://github.com/phpspec/phpspec/compare/9.0.0-beta.8...9.0.0-beta.9)

### Fixed
 - `generate` honours a file path named in the instruction and picks the artifact from its extension — a `.feature` request is written as Gherkin, never a spec — and infers a feature, spec, or code target from the wording when no path is given.
 - `generate` rejects a spec the model wrote in phpspec-8 ObjectBehavior syntax instead of writing invalid DSL.
 - Pair `/next` no longer leaks the machine-readable `{type, target, reason}` suggestion JSON into its conversational reply.

## [9.0.0-beta.8](https://github.com/phpspec/phpspec/compare/9.0.0-beta.7...9.0.0-beta.8)

### Added
 - `--format=agent` machine-readable JSON for coding agents; with `describe --agent`, `exemplify --agent`, and `--accept-offers`
 - Slash commands in pair mode: `/describe`, `/exemplify`, `/run`, `/next`, `/generate`, `/clear` (joining `/swap`, `/help`, `/quit`)
 - Generate command and `/generate` to turn a plain-English instruction into a spec example or code
 - Ghost-text next step, the prompt pre-fills a dim suggestion of the next command (Right-arrow/Tab accepts)
 - `next` follows outside-in, feature-first TDD — favours story tests and advises the next baby step from real suite state; works without AI.

### Fixed
 - Native PHP 8.4/8.5 deprecations in mock generation (nullable `?object` constructor param; dropped `setAccessible()`).
 - Pair `ai:` status reflects whether the provider actually started (`ai: on` / `ai: unavailable` / `ai: off`)

## [9.0.0-beta.7](https://github.com/phpspec/phpspec/compare/9.0.0-beta.6...9.0.0-beta.7)

### Added
 - Roles & /swap — you drive or the AI drives (one artifact per turn); the navigator never writes files.
 - Grounded & self-verifying — it sees the suite's real red/green each turn and auto-runs specs after its own change, instead of guessing.
 - Designs in conversation — clarify → plan → confirm → make the change → present the diff.
 - Grows specs, never overwrites — describe + add_example; drops-examples or phpspec 8 ObjectBehavior writes are rejected.
 - Plus: inspect_symbol, bounded long sessions, a suite-state greeting, real generated-code diffs, unified chooser, ai.max_tokens.

### Fixed
 - Fixed — next no longer loops on an existing spec; code generation won't offer to create a class/spec that already exists.

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
