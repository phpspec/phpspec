# Extensions

PhpSpec has a lightweight extension system. Extensions are Composer packages that provide new formatters, matchers, commands, or event listeners. You install them with Composer and register the class in `phpspec.yml`.

## Quick Start

1. Install the package:

```bash
composer require --dev acme/phpspec-nyan-formatter
```

2. Register it in `phpspec.yml`:

```yaml
extensions:
  formatters:
    - Acme\PhpSpecNyanFormatter\NyanFormatter
```

3. Use it:

```bash
vendor/bin/phpspec run --format nyan
```

That's it. Composer handles autoloading, PhpSpec handles instantiation.

## Configuration

All extensions are registered under the `extensions` key in `phpspec.yml`:

```yaml
extensions:
  formatters:
    - Acme\PhpSpecNyanFormatter\NyanFormatter
    - Acme\PhpSpecGithub\GithubActionsFormatter
  matchers:
    - Acme\PhpSpecMatchers\ValidJsonMatcher
    - Acme\PhpSpecMatchers\ValidUuidMatcher
  commands:
    - Acme\PhpSpecLint\LintCommand
  listeners:
    - Acme\PhpSpecProfiler\SlowTestReporter
```

Each entry is a fully-qualified class name. The class must implement the appropriate extension interface and be autoloadable via Composer.

## Extension Types

### Formatters

Custom output formatters. Each example and each scenario reaches the formatter
as an `Outcome`, by how it ended:

```php
<?php

namespace Acme\PhpSpecGithub;

use PhpSpec\Extensions\FormatterExtension;
use PhpSpec\Result\Outcome;

class GithubActionsFormatter extends FormatterExtension
{
    public function getName(): string
    {
        return 'github';
    }

    public function formatPass(Outcome $outcome): string
    {
        return "::notice ::{$outcome->name()}";
    }

    public function formatFail(Outcome $outcome): string
    {
        $at = $outcome->failedAt ?? $outcome->writtenAt;

        return sprintf('::error file=%s,line=%d,title=%s::%s', $at?->file, $at?->line, $outcome->name(), $outcome->message);
    }

    public function formatPending(Outcome $outcome): string
    {
        return "::warning ::{$outcome->name()} ({$outcome->reason})";
    }
}
```

An `Outcome` carries:

| Member | What it holds |
|---|---|
| `kind` | `Kind::Example` or `Kind::Scenario` |
| `name()` | The titles that lead to it, joined as `--filter` matches them: `Calc > when empty > adds`, `Calc > Subtracts` |
| `title()`, `titles` | Its own title; every title, its own last |
| `state` | `State::Passed`, `Failed`, `Errored`, `Pending`, `Skipped`, `Risky` or `Undefined`; `failsTheRun()` says whether it fails the run |
| `message` | What went wrong, for one that failed or errored, or a scenario with a step no definition matches |
| `reason` | Why it was left pending or skipped, when it said; `no expectation` for a risky example |
| `failedAt` | Where it went wrong, a `Location` with `file` (from the project root) and `line`: the failing expectation, or the project's code an error came from; null when nothing did |
| `writtenAt` | Where it is written: its `it()` line, or its `Scenario:` line in the feature file |
| `duration` | Seconds it took; a scenario's are its steps' |
| `step` | For a scenario, the step that decided how it ended |

A scenario ends as its deciding step did (see [Step States](story-bdd.md#step-states)).
Each outcome reaches one method by its state: `formatPass` for passed and
risky, `formatFail` for failed and undefined, `formatError` for errored,
`formatPending` for pending and `formatSkipped` for skipped. `formatError`
falls back to `formatFail` and `formatSkipped` to `formatPending`; override
`format(Outcome $outcome)` to take every state in one place.
`formatSummary(array $outcomes)` writes the closing line from every outcome it
was handed: `2 examples, 3 scenarios (4 passed, 1 failed)` unless overridden.

Use with `--format github` or set as default:

```yaml
format: github
extensions:
  formatters:
    - Acme\PhpSpecGithub\GithubActionsFormatter
```

### Matchers

Custom matchers extend PhpSpec's `expect()` vocabulary.

```php
<?php

namespace Acme\PhpSpecMatchers;

use PhpSpec\Extensions\MatcherExtension;

class ValidJsonMatcher extends MatcherExtension
{
    public function getName(): string
    {
        return 'toBeValidJson';
    }

    public function match(mixed $actual, mixed ...$args): bool
    {
        if (!is_string($actual)) {
            return false;
        }

        json_decode($actual);
        return json_last_error() === JSON_ERROR_NONE;
    }

    public function failureMessage(mixed $actual): string
    {
        return "Expected valid JSON, got: " . substr((string) $actual, 0, 50);
    }

    public function negatedFailureMessage(mixed $actual): string
    {
        return "Expected invalid JSON, but it was valid";
    }
}
```

Use in specs:

```php
it('returns valid JSON', function () {
    expect('{"name": "phpspec"}')->toBeValidJson();
    expect('not json')->not()->toBeValidJson();
});
```

### Commands

Add new CLI commands.

```php
<?php

namespace Acme\PhpSpecLint;

use PhpSpec\Extensions\CommandExtension;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class LintCommand extends CommandExtension
{
    public function getName(): string
    {
        return 'lint';
    }

    public function getDescription(): string
    {
        return 'Check spec files for common mistakes';
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        // Your lint logic here
        $output->writeln('All specs look good.');
        return 0;
    }
}
```

Run with:

```bash
vendor/bin/phpspec lint
```

### Listeners

Hear how the run goes: when the suite begins and ends, and each example and
each scenario as an `Outcome` (see [Formatters](#formatters)), once its spec
file or feature has run. Stories and `--parallel` runs are heard the same
way, each outcome once.

```php
<?php

namespace Acme\PhpSpecProfiler;

use PhpSpec\Extensions\ListenerExtension;
use PhpSpec\Result\Outcome;

class SlowTestReporter extends ListenerExtension
{
    private array $slow = [];

    public function completed(Outcome $outcome): void
    {
        if ($outcome->duration > 0.5) {
            $this->slow[] = [$outcome->name(), $outcome->duration];
        }
    }

    public function afterSuite(): void
    {
        if (empty($this->slow)) {
            return;
        }

        usort($this->slow, fn($a, $b) => $b[1] <=> $a[1]);
        echo "\nSlow examples:\n";
        foreach ($this->slow as [$name, $duration]) {
            printf("  %.3fs  %s\n", $duration, $name);
        }
    }
}
```

Every method is optional:

| Method | Told |
|---|---|
| `beforeSuite()` | Once, before anything runs |
| `afterSuite()` | Once, after everything has been reported |
| `completed(Outcome $outcome)` | Each example and scenario; by default it calls the method for how it ended |
| `onPass(Outcome $outcome)` | Passed, or an example that checked nothing |
| `onFail(Outcome $outcome)` | Failed, or a scenario with a step no definition matches |
| `onError(Outcome $outcome)` | Errored |
| `onPending(Outcome $outcome)` | Left pending |
| `onSkipped(Outcome $outcome)` | Skipped |

### Browsers

The browser behind `visit()` and the other [browser DSL](browser.md) functions
is replaceable. Name a class implementing `PhpSpec\Browser\Browser`; it must
map whatever it drives onto the canonical `PhpSpec\Browser\Response`, which
keeps every response matcher working unchanged:

```yaml
extensions:
  browser: Acme\BrowserKitBrowser
```

Unlike the other types the key is singular: exactly one browser drives the
DSL. The config's choice beats auto-discovery, and two auto-discovered
browsers with no config choice are refused by name. An extension cannot yet
declare its own config keys (a kernel path, say); that hook does not exist.

### AI Tools

`tools` classes implement `PhpSpec\Extensions\ToolProviderInterface` and
return AI tools the [pair](pair.md) assistant can call. Currently consumed by
`pair` only.

```yaml
extensions:
  tools:
    - Acme\DeployStatusTools
```

## Auto-Discovery

Extensions can opt into auto-discovery so users don't need to add them to `phpspec.yml` manually. Add a `phpspec` key to your package's `composer.json`:

```json
{
    "name": "acme/phpspec-nyan-formatter",
    "extra": {
        "phpspec": {
            "formatters": ["Acme\\PhpSpecNyanFormatter\\NyanFormatter"]
        }
    }
}
```

When the package is installed, PhpSpec reads the `extra.phpspec` metadata from all installed packages and registers the extensions automatically.

To disable auto-discovery for a specific package:

```yaml
extensions:
  disabled:
    - acme/phpspec-nyan-formatter
```

## Writing an Extension Package

A minimal extension package has three files:

```
acme/phpspec-nyan-formatter/
  composer.json
  src/
    NyanFormatter.php
```

**`composer.json`:**

```json
{
    "name": "acme/phpspec-nyan-formatter",
    "description": "Nyan cat formatter for PhpSpec",
    "type": "phpspec-extension",
    "require": {
        "php": "^8.3",
        "phpspec/phpspec": "^9.0"
    },
    "autoload": {
        "psr-4": {
            "Acme\\PhpSpecNyanFormatter\\": "src/"
        }
    },
    "extra": {
        "phpspec": {
            "formatters": ["Acme\\PhpSpecNyanFormatter\\NyanFormatter"]
        }
    }
}
```

**`src/NyanFormatter.php`:**

```php
<?php

namespace Acme\PhpSpecNyanFormatter;

use PhpSpec\Extensions\FormatterExtension;
use PhpSpec\Result\Outcome;

class NyanFormatter extends FormatterExtension
{
    public function getName(): string
    {
        return 'nyan';
    }

    public function formatPass(Outcome $outcome): string
    {
        return "~=[,,_,,]:3 {$outcome->name()}";
    }

    public function formatFail(Outcome $outcome): string
    {
        return "=[,,_,,]= {$outcome->name()}\n  {$outcome->message}";
    }
}
```

Publish to Packagist, and users get it with:

```bash
composer require --dev acme/phpspec-nyan-formatter
vendor/bin/phpspec run --format nyan
```

## Extension Interfaces

Each extension type has a base class with the minimum contract:

| Type | Base Class | Required Methods |
|---|---|---|
| Formatter | `FormatterExtension` | `getName()`, `formatPass()`, `formatFail()` |
| Matcher | `MatcherExtension` | `getName()`, `match()`, `failureMessage()` |
| Command | `CommandExtension` | `getName()`, `execute()` |
| Listener | `ListenerExtension` | None; override what it should hear |

All base classes live in `PhpSpec\Extensions\`.

## Example: Full Project Config

```yaml
spec_path: spec
src_path: src

format: pretty

extensions:
  formatters:
    - Acme\PhpSpecGithub\GithubActionsFormatter
  matchers:
    - Acme\PhpSpecMatchers\ValidJsonMatcher
    - Acme\PhpSpecMatchers\ValidUuidMatcher
  commands:
    - Acme\PhpSpecLint\LintCommand
  listeners:
    - Acme\PhpSpecProfiler\SlowTestReporter
```

## Design Principles

- **Composer-native.** Extensions are regular Composer packages with PSR-4 autoloading. No custom loaders, no file paths in config.
- **Config-driven.** Registration happens in `phpspec.yml` by FQCN. Auto-discovery via `composer.json` `extra` is optional.
- **Minimal interfaces.** Base classes have sensible defaults. Override only what you need.
- **No magic.** Extensions don't modify PhpSpec internals. They plug into defined extension points: formatters, matchers, commands, and event listeners.
