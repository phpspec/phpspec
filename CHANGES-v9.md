# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/)
and this project adheres to [Semantic Versioning](http://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed since rc.5
 - A double of a class is made without running the class's constructor, where it used to run it with arguments PhpSpec made up. Properties keep the defaults they declare; a promoted property, or any other the constructor sets, stays uninitialised, so a final method that reads one needs a real instance. A double of an interface built on `DateTimeImmutable` or `Exception` is still initialised as that base is
 - Questions are asked only at a terminal. With a pipe or a file on stdin (`docker compose exec -T`, CI, `cmd | phpspec`), every offer is declined and the run says how to take it, where it used to read the input as answers or wait on it. To pipe answers on purpose, set `SHELL_INTERACTIVE=1`; `--no-interaction` still wins over it, and `--accept-offers` works as before. A piped `pair` session needs `SHELL_INTERACTIVE=1` too, and `refactor` carries a plan out unattended only with an explicit `--no-interaction`

## [9.0.0-rc.1](https://github.com/phpspec/phpspec/compare/8.3.1...9.0.0-rc.1)

PhpSpec 9 is a rewrite. This is what it brings over PhpSpec 8.

### Breaking changes
 - Specs are closures: `describe()`, `context()`, `it()`, `this()` and `expect()` are global functions, and a spec file ends in `.spec.php`. `ObjectBehavior`, the `$this->shouldReturn()` style and the Prophecy doubles are gone
 - Test doubles are built in: the dependencies on Prophecy, symfony/event-dispatcher, symfony/process, symfony/finder, doctrine/instantiator, sebastian/exporter and phpspec/php-diff are gone
 - PHP 8.3 or newer

### Spec BDD
 - `describe()`, `context()`, `it()` and `this()`, a value built the first time an example reads it, once per example; `beforeAll`, `beforeEach`, `afterEach` and `afterAll` hooks; `xit()` and `xdescribe()` for pending, `fit()` and `fdescribe()` for focus, which a run without a terminal fails on; `pending()` and `skip()` with a reason; `it("title")` with no body is pending
 - `expect($value)` with over thirty matchers, negated by `not()`, chainable, each judged on the line it is written; `toThrow()` runs its callable where it is written; an example that makes no expectation is reported as risky
 - Custom matchers through `addMatcher()`, and extensions that add formatters, matchers, commands and listeners
 - A failure shows the value wanted and the value produced, typed, and two long strings around their first difference

### Test doubles
 - `mock()` makes a strict double: a call nothing declared fails the example naming the call and where it came from. `dummy()` makes a lenient one, and a stub ending in `->ignoreOthers()` leaves the rest of a double lenient
 - `allow($double->method($args))` with `->toReturn()`, `->andReturn()`, `->toReturnUsing()` and `->toThrow()`, answering in declaration order and held to the real method's return type; `allow()` alone declares a call as expected
 - Mocks: `expect($double->method($args))->toBeCalled()` before the act, with `->once()`, `->twice()`, `->exactly(N)->times()`, `->atLeast(N)->times()` and `->atMost(N)->times()`, or `not()->toBeCalled()`. Spies: `toHaveBeenCalled()` after the act, judged at once, with the same counts
 - A call is read as PHP reads it, arguments and matchers included: `any()`, `type()`, `anInstanceOf()`, `arrayIncluding()`, `satisfy()`, `callback()`, `startWith()` and `cetera()`; an object argument matches an equal object
 - A double is injected by type hint into an example or a `this()` closure, and sits on `$this` under the parameter's name

### Story BDD
 - Gherkin `.feature` files run by PhpSpec itself, with no Behat: `given()`, `when()`, `then()`, `step_and()` and `step_but()` definitions under `features/steps/`; the placeholders `{string}`, `{int}`, `{float}`, `{word}` and `{*}`; Scenario Outlines, data tables (`DataTable`, `asClass()`), doc strings, `Background`, tags selected with `--tags`, and hooks before and after features, scenarios and steps
 - Helper classes under `features/support/`; a `#[ParameterType]` function makes the object a step asks for from its captures, and a `#[DocStringType]` function turns a doc string for the parameter named after it
 - Undefined steps are generated on offer, all of them into one steps file; `PhpSpec\attach()` hands over context to report with a failure

### Running
 - `run` takes spec files, directories, feature files, `file:LINE` and `file@ID`; `--filter` matches the path of titles, `--tags` a tag expression; `--story`, `--all`, `--paths-from`, `--order=random` with `--seed`, `--profile`, and `--stop-on-failure`, `-error`, `-warning`, `-deprecation`, `-notice`, `-pending`, `-skipped` and `-problems`. A value an option cannot take is refused before anything runs, and a run that selects nothing fails
 - `--parallel[=N]` runs files in worker processes that report everything a single process does
 - Formatters: pretty, dot, TAP, JUnit, HTML, and `--format=agent`, a JSON Lines stream for coding agents: ids and names, a re-run command per entry, both sides of a failed expectation with a diff, the code generation on offer, warnings, deprecations and notices, and a `fatal` with a remedy. `--accept-offers` and `phpspec accept <id>` apply what a run offered
 - PHP warnings, deprecations and notices are reported on the example or step that raised them, and counted
 - Configuration in `phpspec.yml`, `.yaml`, `.json` or `.php`, or the file `--config` names: paths, suites, `autoload`, `default_namespace`, `bootstrap`, `format`, the stop options, `steps_path`, `features_path`, `coverage_adapter`, `guard`, `ai` and `extensions`; a mistyped key is refused naming the file

### Coverage and guard
 - Text, HTML, Clover and JSON coverage reports from PCOV or Xdebug, whichever is on (`coverage_adapter` names one), `--coverage-min` as a gate and `--coverage-src` to scope; the JSON report attributes lines to examples, with timing and checksums, for mutation testing
 - `phpspec guard`: a run refuses a change whose new logic no example reaches, and `guard --check` asks the same question in CI

### Code generation
 - `describe` and `exemplify` write specs; a run offers the classes, interfaces, methods, specs and steps it finds missing, and writes nothing without an answer; `--fake` fills a method with the value its spec expects; generated code lands where Composer maps its namespace

### AI pair programming
 - `pair`, `next`, `generate` and `refactor` work with a configured provider: a navigator or driver in a REPL with slash commands, the next TDD step from the suite's real state, a spec or implementation from an instruction shown as a diff, and refactoring through a plan of steps that keeps the suite green; every prompt is an editable file, and every command speaks `--format=agent`
 - `phpspec api` describes the DSL, the matchers and the timing rules from the code itself

### Browser testing
 - `visit()`, `get()`, `post()`, `put()`, `patch()` and `delete()` with response matchers such as `toBeOk()`, `toHaveStatus()` and `toHavePath()`, which take any PSR-7 response; the browser behind them is an extension point
