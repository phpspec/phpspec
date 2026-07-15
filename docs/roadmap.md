# Roadmap & Status

## Current State

PhpSpec is a fully-featured Spec BDD and Story BDD framework for PHP 8.2+. It supports a Jasmine/RSpec-style DSL, built-in mocking, code generation, Gherkin feature files, code coverage, and multiple output formatters.

### What Works

#### Spec BDD
- `describe` / `context` / `it` / `its` / `let` DSL
- Nested contexts with proper indentation in output
- 30+ matchers with negation and custom matcher support
- Lifecycle hooks: `beforeEach`, `afterEach`, `beforeAll`, `afterAll`
- Pending/skipped: `xit`, `xdescribe`, `xcontext`, `pending()`
- Focused: `fit`, `fdescribe`, `fcontext` (per-context resolution)
- Type-hinted mock injection in `it()` and `let()` closures

#### Mocking
- Test doubles via `mock()` with auto-generated classes
- Mock verification: `toBeCalled()`, `toBeCalledWith()`, `toBeCalledTimes()`, `toHaveBeenCalled()`
- Stubbing: `allow($mock->method())->toReturn(value)`
- Argument matchers: `any()`, `type()`, `callback()`
- Support for nullable, union, intersection types, and enums

#### Story BDD
- Gherkin `.feature` files with Given/When/Then step definitions
- Background, Scenario Outline with Examples tables
- DataTable support with `asClass()` hydration
- Tags on features and scenarios
- Before/After hooks for scenarios
- Step generation for undefined steps

#### Code Generation
- Spec generation (`describe` command)
- Method example generation (`exemplify` command, `describe -e`)
- Auto-class generation (from spec errors)
- Interface generation (from mock errors)
- Method stub generation (from method-not-found errors)
- `--fake` mode for auto-generating method bodies
- Non-interactive apply for agents/CI (`--accept-offers`, `--accept-offers --fake`)

#### CLI & Reporting
- Pretty, Dot, TAP, JUnit XML, HTML, and Agent (JSON) formatters
- Machine-readable agent workflow (`--format=agent`, `describe/exemplify --agent`)
- Parallel execution (`--parallel[=N]`)
- Verbose mode (`-v`) with per-example timing
- Quiet mode (`-q`)
- Profile mode (`--profile`)
- Random ordering (`--order=random`, `--seed`)
- Filter (`--filter`), path list (`--paths-from`), line-targeted runs (`spec.php:LINE`)
- Suite selection (`--all`, `--story`)
- Stop-on family (`--stop-on-failure`, `-error`, `-warning`, `-deprecation`, `-notice`, `-skipped`, `-problems`)
- Bootstrap file support

#### Code Coverage
- Text report in terminal
- HTML report with color-coded source views
- Clover XML for CI integration
- Minimum threshold enforcement (`--coverage-min`)

#### AI-Powered Pair Programming
- `pair` command: interactive AI pair programmer REPL
- `next` command: AI-powered next-step suggestions
- `refactor` command: behaviour-preserving AI refactoring
- Configurable providers: Anthropic, OpenAI, Google

#### Extensions
- Extension interfaces for matchers, formatters, commands, and listeners
- `ExtensionLoader` for discovering and loading extensions

#### Parallel Execution
- `--parallel[=N]` option with process-based worker pool
- Result aggregation and ordered output

#### Configuration
- `phpspec.yaml`, `phpspec.yml`, `phpspec.json`, or `phpspec.php` config files
- Named suites with per-suite paths, src, and steps
- PSR-4 autoload mappings
- Auto-detection of `vendor/autoload.php`

---

## TODO

### Mocking
- [ ] Spy support (call through to real implementation + record)
- [ ] Partial mocks
- [ ] Handle readonly classes in mock generation

### Runner & Lifecycle
- [x] Parallel spec execution (`--parallel[=N]`)

### Code Generation
- [ ] Constructor argument detection from `let` usage
- [ ] Template customisation

### Developer Experience
- [ ] Watch mode (re-run on file change)

### Internal / Technical Debt
- [ ] Static `Dispatcher` -- consider instance-based dispatcher for isolation
- [ ] Subscriber accumulation -- subscribers are never removed, events from inner examples can leak to outer subscribers
