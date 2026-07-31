# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/)
and this project adheres to [Semantic Versioning](http://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed
 - Pair's conversation runs through the one `Agent::chat()` pipeline: a persistent Transcript carries the history, the PairToolExecutor runs the tools live, and pair's own provider loop is deleted
 - Navigator and driver are command manifests (`commands/navigator.txt`, `commands/driver.txt`): each role's tool surface, temperature, and turn ceiling are project-overridable data, and the live tool descriptions (run_specs, inspect_symbol, ask_user, read_file, list_files) are editable prompt files
 - Pair turns are step-grounded: the situation report carries the resolved TDD step, and pair's suggest_next speaks the unified seven-type vocabulary, with steps and implement suggestions getting /generate ghosts
 - Pair model and token settings resolve through the unified config-beats-manifest precedence, and a provider failure surfaces as readable prose instead of an error banner

### Added
 - Pair conversations are recorded: every exchange lands in .phpspec/ai/last-request.json and the whole session accumulates in last-session.json, so a live pair session can be promoted straight to a replayable eval

### Fixed
 - A step whose code throws reports under Errors with its type, code excerpt, and location, and tallies as "N errored"; only a failed expectation reads as a Failure
 - PHP warnings raised inside a step land in the Warnings section with their location instead of leaking raw to the terminal
 - The ai section is validated as documented: unknown or misspelled keys get a did-you-mean, hyphenated spellings are accepted, wrong-typed values are named instead of silently dropped, and a missing key is named precisely, never reported as a missing section
 - Forgetting the ai provider names the installed papi package as the answer, instead of silently assuming openai and then blaming a missing composer package
 - An ollama config works without api_key and its base_url reaches the provider; all six providers (google, anthropic, openai, grok, deepseek, ollama) are documented

## [9.0.0-beta.16](https://github.com/phpspec/phpspec/compare/9.0.0-beta.15...9.0.0-beta.16)

### Added
 - Any AI prompt can be overridden per project under .phpspec/prompts/; prose-only command overrides inherit the shipped tools and params, and the capture log marks overridden names
 - `--format=agent` on every command: `next` emits its suggestion with the exact command that acts on it, `generate` applies and emits receipts, `describe`/`exemplify` accept it as the canonical spelling of `--agent` (now a deprecated alias)

### Changed
 - The pipeline's one verb is `Agent::chat(command, instruction)`; commands pass a name and the agent resolves the manifest through its own prompt library
 - End-of-run detail is grouped into Failures, Errors, Warnings, Deprecations, and Skipped sections, and the dot formatter tells the same detailed story as pretty
 - A failure reads as an aligned pair labeled by its matcher (expected / to contain), long multiline values capped head[...]tail with newlines escaped
 - Offered-code diffs show three unchanged lines around each change with one elision line between distant stretches, never the whole file

### Fixed
 - The pretty formatter reports only the failed expectation; passing ones before it no longer print a bogus sentinel Failure line
 - Failure headlines clip multiline or overlong values to a first-line excerpt; the full value appears once, under expected/got
 - Two step definitions sharing a title now error at load, naming both locations; matching is keyword-blind, so the duplicate could only shadow silently
 - A step used twice in a scenario generates one definition, not two
 - AI-written steps respect the vocabulary too: content redefining a title another steps file owns is rejected with a pointer to reuse it, and scaffolds skip titles defined elsewhere
 - The run agent document reports the real random seed and what was run, never `seed: null` and `suite: "default"` placeholders

## [9.0.0-beta.15](https://github.com/phpspec/phpspec/compare/9.0.0-beta.14...9.0.0-beta.15)

### Added
 - Applied refactorings are journalled (.phpspec/ai/journal.jsonl); reversing one demands a stated rationale

### Changed
 - The navigator offers directly; the chooser is the question, never "shall I offer?"
 - `refactor` shows the change and asks before applying; non-interactive runs keep auto-apply
 - `next` reads the journal: a polished, unchanged class is never re-suggested, and the model proposes the next scenario or feature instead
 - A generated feature carries real model-written scenarios; the skeleton stands in when the model cannot be reached, with the failure shown beside it
 - Every `next` suggestion offers to act (example, steps, implement, and refactor included), and the model can suggest steps and implement too
 - A feature whose step bodies are pending reads as the working story: `next` steers to finishing it, never to a new feature
 - When the working story is red, `next` shows the model its actual files, so the advice names the real gap

### Fixed
 - Accepting a feature suggestion from `next` runs `generate`, so it writes the feature instead of describing a spec at the project root
 - Determined steps offer too: `next` asks before writing the steps or implementing the failing class, instead of printing advice and exiting
 - An accepted example or implement offer forwards the suggestion's reason, so `generate` makes the change the suggestion meant
 - A named class with spec wording routes as a spec ask even when the sentence also says "feature"
 - A degenerate failure whose subject matches its example is named once, not twice
 - A failing expectation on a list of objects reports the failure instead of crashing the message formatter
 - Auto-verify runs the whole suite when the change touches a feature or steps file
 - A verified change ends the turn; no fresh suggestion rides on its back
 - `refactor` runs its baseline with the installed phpspec, wherever it lives (vendor installs failed to launch it and blamed the specs)
 - Standalone hints name the binary you invoked, never a hardcoded bin/phpspec

## [9.0.0-beta.14](https://github.com/phpspec/phpspec/compare/9.0.0-beta.13...9.0.0-beta.14)

### Added
 - On a green suite, `next` can recommend refactoring, pointing at (or ghosting) `refactor` with the target

### Fixed
 - One-shot AI commands declare their tools on the wire again (they were sent as zero declarations, so the model answered empty)
 - Composer now refuses papi-ai packages older than 0.13
 - `generate` places a bare filename under its configured directory (features, spec, or src), never the project root
 - Asking `generate` for step bodies (or to grow an existing feature) consults the model instead of re-emitting a no-op scaffold
 - A `/generate` chooser note now runs a follow-up round with that note as the instruction
 - Pair delegates commands with their arguments actually bound, so `/refactor App/TodoList` runs

## [9.0.0-beta.13](https://github.com/phpspec/phpspec/compare/9.0.0-beta.12...9.0.0-beta.13)

### Added
 - Pair commands work as plain English, with or without the slash
 - Choice menu accepts a typed amendment on Tab, and the AI reads it

### Fixed
 - The navigator is capped at one offer and one suggestion per turn
 - Options on pair `/run` (e.g. `--all`) now reach the runner

## [9.0.0-beta.12](https://github.com/phpspec/phpspec/compare/9.0.0-beta.11...9.0.0-beta.12)

### Added
 - The pair navigator can OFFER a concrete change: the human sees it as a diff and accepts or declines through the numbered chooser (1. Yes / 2. Always / 3. No), the accepted change lands through the shared write gate and auto-verifies, and unbidden writes stay forbidden. Spec offers must grow the spec, exactly like writes.
 - Pair `/next` pre-fills the prompt with a matching `/generate` ghost built from the suggestion the AI registers (via `suggest_next`), so acting on the advice is Tab and Enter, never retyping it.

## [9.0.0-beta.11](https://github.com/phpspec/phpspec/compare/9.0.0-beta.10...9.0.0-beta.11)

### Fixed
 - The google default model is `gemini-3.1-pro-preview`: its predecessor `gemini-3-pro-preview` was retired from the API and answered every request with a 404. Set `ai.model` to choose differently.
 - `generate` resolves a bare class name against the project tree ("the TodoList spec" finds `App\TodoList`), so the model is shown the real current file and the proposal updates it in place instead of creating a flat sibling.
 - `generate` grounds the model in a labelled, deeper file listing (a namespaced project no longer renders as a bare `App/`), and one-shot commands default to a 16384 output-token ceiling so a reasoning model's thinking cannot come back as an empty answer.
 - A failed pair `/generate` points back to plain English, which holds the conversation context the one-shot does not.

## [9.0.0-beta.10](https://github.com/phpspec/phpspec/compare/9.0.0-beta.9...9.0.0-beta.10)

### Added
 - One agent pipeline behind every AI command: the current TDD step is resolved deterministically from the instruction and the suite state, fully determined artifacts are generated without a model call (`generate the steps` writes the step definitions for the last-touched feature, offline), and every exchange is captured to `.phpspec/ai/last-request.json` for debugging.
 - Prompts are editable files: command manifests (`commands/*.txt` with the tools, answer channel, and model params in YAML frontmatter), per-step instructions, shared syntax primers composed with a one-line `@include` directive, and every pair write-tool description.
 - `ai.effort` passes a reasoning effort through to the provider; `ai.model` and `ai.max_tokens` always beat the shipped defaults.
 - The pair status bar shows the resolved model next to the provider.
 - The tool answer channel is enforced at the provider (`toolChoice`, papi-core/google 0.13); a single corrective re-ask remains as the fallback.

### Changed
 - `next` answers with a `suggest_next` tool call instead of JSON parsed out of prose, and suggests the determined step directly (no model call) when the suite state determines it: undefined steps, a failing example, or a pending gap.
 - Every AI write, pair mode included, is a proposal applied through one write gate after confirmation, and all spec-content guards share one ObjectBehavior detector.
 - Default models refreshed per provider: `gemini-3-pro-preview`, `claude-sonnet-5`, `gpt-5.1`, `grok-4`.

### Fixed
 - A live provider failure (a bad key, an HTTP error) surfaces as a message instead of crashing `generate` and `next`.
 - Pair mode's project context honours a configured `features_path`.

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
