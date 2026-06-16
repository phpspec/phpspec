# Extensions

PhpSpec has a lightweight extension system. Extensions are Composer packages that provide new formatters, matchers, commands, or event listeners. You install them with Composer and register the class in `phpspec.yaml`.

## Quick Start

1. Install the package:

```bash
composer require --dev acme/phpspec-nyan-formatter
```

2. Register it in `phpspec.yaml`:

```yaml
extensions:
  formatters:
    - Acme\PhpSpecNyanFormatter\NyanFormatter
```

3. Use it:

```bash
bin/phpspec run --format nyan
```

That's it. Composer handles autoloading, PhpSpec handles instantiation.

## Configuration

All extensions are registered under the `extensions` key in `phpspec.yaml`:

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

Custom output formatters for spec results.

```php
<?php

namespace Acme\PhpSpecGithub;

use PhpSpec\Extensions\FormatterExtension;

class GithubActionsFormatter extends FormatterExtension
{
    public function getName(): string
    {
        return 'github';
    }

    public function formatPass(string $title): string
    {
        return "::notice ::$title";
    }

    public function formatFail(string $title, string $message): string
    {
        return "::error ::$title - $message";
    }

    public function formatPending(string $title): string
    {
        return "::warning ::$title (pending)";
    }

    public function formatError(string $title, string $message): string
    {
        return "::error ::$title - $message";
    }

    public function formatSummary(int $total, int $passed, int $failed, int $pending, int $errors): string
    {
        return "$total examples ($passed passed, $failed failed, $pending pending, $errors errors)";
    }
}
```

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
bin/phpspec lint
```

### Listeners

React to lifecycle events during a spec run.

```php
<?php

namespace Acme\PhpSpecProfiler;

use PhpSpec\Extensions\ListenerExtension;

class SlowTestReporter extends ListenerExtension
{
    private array $slow = [];

    public function afterExample(string $title, float $duration): void
    {
        if ($duration > 0.5) {
            $this->slow[] = [$title, $duration];
        }
    }

    public function afterSuite(): void
    {
        if (empty($this->slow)) {
            return;
        }

        usort($this->slow, fn($a, $b) => $b[1] <=> $a[1]);
        echo "\nSlow examples:\n";
        foreach ($this->slow as [$title, $duration]) {
            printf("  %.3fs  %s\n", $duration, $title);
        }
    }
}
```

Available events:

| Event | Arguments |
|---|---|
| `beforeSuite()` | -- |
| `afterSuite()` | -- |
| `beforeSpec(string $title)` | Spec file title |
| `afterSpec(string $title)` | Spec file title |
| `beforeExample(string $title)` | Example title |
| `afterExample(string $title, float $duration)` | Example title and duration in seconds |
| `onPass(string $title)` | Passed example |
| `onFail(string $title, string $message)` | Failed example with error message |
| `onError(string $title, string $message)` | Errored example with exception message |
| `onPending(string $title)` | Pending example |
| `onSkipped(string $title)` | Skipped example |

## Auto-Discovery

Extensions can opt into auto-discovery so users don't need to add them to `phpspec.yaml` manually. Add a `phpspec` key to your package's `composer.json`:

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
        "php": "^8.2",
        "phpspec/phpspec": "^1.0"
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

class NyanFormatter extends FormatterExtension
{
    public function getName(): string
    {
        return 'nyan';
    }

    public function formatPass(string $title): string
    {
        return "~=[,,_,,]:3 $title";
    }

    public function formatFail(string $title, string $message): string
    {
        return "=[,,_,,]= $title\n  $message";
    }
}
```

Publish to Packagist, and users get it with:

```bash
composer require --dev acme/phpspec-nyan-formatter
bin/phpspec run --format nyan
```

## Extension Interfaces

Each extension type has a base class with the minimum contract:

| Type | Base Class | Required Methods |
|---|---|---|
| Formatter | `FormatterExtension` | `getName()`, `formatPass()`, `formatFail()` |
| Matcher | `MatcherExtension` | `getName()`, `match()`, `failureMessage()` |
| Command | `CommandExtension` | `getName()`, `execute()` |
| Listener | `ListenerExtension` | At least one event method |

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
- **Config-driven.** Registration happens in `phpspec.yaml` by FQCN. Auto-discovery via `composer.json` `extra` is optional.
- **Minimal interfaces.** Base classes have sensible defaults. Override only what you need.
- **No magic.** Extensions don't modify PhpSpec internals. They plug into defined extension points -- formatters, matchers, commands, and event listeners.
