# Configuration

PhpSpec can be configured via a config file in the project root. The following formats are supported, checked in priority order:

1. `phpspec.yaml`
2. `phpspec.yml`
3. `phpspec.json`
4. `phpspec.php`

The first file found wins. If none exists, defaults are used.

## phpspec.yaml

```yaml
spec_path: spec
src_path: src
format: pretty
bootstrap: bootstrap.php
stop_on_failure: false
autoload:
  App\: src/App
  Acme\: lib/Acme
```

## phpspec.yml

Same syntax as `phpspec.yaml` — just an alternative extension.

## phpspec.json

```json
{
    "spec_path": "spec",
    "src_path": "src",
    "format": "pretty",
    "bootstrap": "bootstrap.php",
    "stop_on_failure": false,
    "autoload": {
        "App\\": "src/App",
        "Acme\\": "lib/Acme"
    }
}
```

## phpspec.php

A PHP file that returns an array:

```php
<?php

return [
    'spec_path' => 'spec',
    'src_path' => 'src',
    'format' => 'dot',
    'stop_on_failure' => true,
];
```

## Configuration Keys

### `spec_path`

Directory containing spec files. Default: `spec`.

```yaml
spec_path: tests/spec
```

### `spec_suffix`

File suffix used to identify spec files. Default: `.spec.php`. Change this if you prefer a different naming convention such as `Spec.php` or `Test.php`.

```yaml
spec_suffix: Spec.php
```

This affects file discovery, spec generation (`describe` command), and title derivation.

### `src_path`

Directory containing source files. Used for code generation and coverage filtering. Default: `src`.

```yaml
src_path: lib
```

### `format`

Default output formatter. Overridden by `--format` CLI flag.

| Value | Formatter |
|---|---|
| `pretty` | Full output with context hierarchy (default) |
| `dot` | One character per example |
| `tap` | TAP (Test Anything Protocol) |
| `junit` | JUnit XML for CI integration |

### `bootstrap`

PHP file to require before running specs. Useful for defining constants, setting up autoloaders, or configuring the environment. Overridden by `--bootstrap` CLI flag.

```yaml
bootstrap: tests/bootstrap.php
```

### `stop_on_failure`

Stop execution after the first failing or erroring spec. Default: `false`. Overridden by `--stop-on-failure` CLI flag.

```yaml
stop_on_failure: true
```

### `stop_on_error`

Stop execution after the first erroring spec. Default: `false`. Overridden by `--stop-on-error` CLI flag.

```yaml
stop_on_error: true
```

### `stop_on_warning`

Stop execution after the first spec that triggers a warning. Default: `false`. Overridden by `--stop-on-warning` CLI flag.

```yaml
stop_on_warning: true
```

### `stop_on_deprecation`

Stop execution after the first spec that triggers a deprecation notice. Default: `false`. Overridden by `--stop-on-deprecation` CLI flag.

```yaml
stop_on_deprecation: true
```

### `stop_on_notice`

Stop execution after the first spec that triggers a notice. Default: `false`. Overridden by `--stop-on-notice` CLI flag.

```yaml
stop_on_notice: true
```

### `stop_on_skipped`

Stop execution after the first skipped spec. Default: `false`. Overridden by `--stop-on-skipped` CLI flag.

```yaml
stop_on_skipped: true
```

### `autoload`

PSR-4 namespace-to-directory mappings. PhpSpec uses these to locate source files for code generation and to autoload classes during spec execution.

```yaml
autoload:
  App\: src/App
  Domain\: src/Domain
  Infrastructure\: src/Infrastructure
```

Each key is a namespace prefix (with trailing `\`), and each value is the directory path relative to the project root.

### `base_url`

Base URL for browser testing. Default: `null`.

```yaml
base_url: http://localhost:8080
```

## Named Suites

Group specs into named suites, each with its own paths and source directory. When no explicit paths are passed to `phpspec run`, all suite paths are loaded.

```yaml
suites:
  unit:
    paths:
      - spec/Unit
    src: src
  integration:
    paths:
      - spec/Integration
    src: src
  acceptance:
    paths:
      - features
    steps:
      - features/steps
```

Each suite supports:

| Key | Description |
|---|---|
| `paths` | Array of directories to scan for spec/feature files |
| `src` | Source directory for code generation and coverage |
| `steps` | Array of directories containing step definition files (reserved for future use) |

When no `suites` key is present, PhpSpec synthesises a default suite from the flat `spec_path` and `src_path` values:

```yaml
# This:
spec_path: spec
src_path: src

# Is equivalent to:
suites:
  default:
    paths:
      - spec
    src: src
```

### Running a specific suite's paths

Pass paths directly to `phpspec run`:

```bash
phpspec run spec/Unit
phpspec run spec/Unit spec/Integration
```

## Auto-Detection

### vendor/autoload.php

PhpSpec automatically detects and requires `vendor/autoload.php` if it exists in the project root. This means Composer-managed projects work out of the box without configuring autoloading.

### Config File Discovery

PhpSpec looks for configuration files in this order:

1. `phpspec.yaml`
2. `phpspec.yml`
3. `phpspec.json`
4. `phpspec.php`

The first file found wins. If none exists, defaults are used.

## CLI Overrides

CLI flags take priority over configuration file values:

| Config Key | CLI Flag |
|---|---|
| `format` | `--format` / `-f` |
| `bootstrap` | `--bootstrap` / `-b` |
| `stop_on_failure` | `--stop-on-failure` |
| `stop_on_error` | `--stop-on-error` |
| `stop_on_warning` | `--stop-on-warning` |
| `stop_on_deprecation` | `--stop-on-deprecation` |
| `stop_on_notice` | `--stop-on-notice` |
| `stop_on_skipped` | `--stop-on-skipped` |
| `spec_path` | Pass path as argument to `run` |

Config and CLI flags are merged — if either is enabled, the condition is active. For example, `stop_on_error: true` in config combined with `--stop-on-warning` on the CLI will stop on both errors and warnings.

## AI Assistant

The `ai` section enables the AI assistant in pair mode (`phpspec pair`) and the refactor command (`phpspec refactor`). When configured, any input that doesn't match a built-in command is sent to the LLM, which can generate specs, features, step definitions, run specs, and answer questions about your codebase.

```yaml
ai:
  provider: anthropic
  api_key: YOUR_API_KEY
```

| Key | Required | Description |
|---|---|---|
| `provider` | Yes | LLM provider: `google`, `anthropic`, or `openai` |
| `model` | No | Model identifier (defaults to provider's recommended model) |
| `api_key` | Yes | API key for the provider. Without this, AI features are disabled. |

Default models per provider: `gemini-2.5-pro` (google), `claude-sonnet-4-20250514` (anthropic), `gpt-4o` (openai).

See [Pair Programming & AI](pair.md) for full documentation on pair mode, the AI assistant, and the refactor command.
