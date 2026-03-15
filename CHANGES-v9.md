# Changelog — PhpSpec 9

## [9.0.0-poc] — Proof of Concept

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
