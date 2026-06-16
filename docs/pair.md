# Pair Programming & AI

PhpSpec includes an interactive pair programming mode and AI-powered refactoring. These features combine the BDD workflow with LLM assistance to help you write specs, features, step definitions, and source code conversationally.

## The `pair` Command

Start an interactive REPL session:

```bash
bin/phpspec pair
```

Or send a single prompt without entering the REPL:

```bash
bin/phpspec pair --prompt "write a spec for a Calculator that adds two numbers"
```

The pair command requires an interactive terminal. It sets up a split-screen layout with a scrolling content area and a fixed input line at the bottom.

### Built-in Commands

These commands work without AI configuration:

| Command | Description |
|---|---|
| `describe <Class>` | Generate a spec file and optionally create the class |
| `exemplify <Class> <method>` | Add a method example to an existing spec |
| `run [path]` | Run specs and offer code generation for failures |
| `clear` | Clear the terminal |
| `/help` | Show available commands and AI status |
| `/quit`, `/exit` | Exit pair mode |

Commands mirror the CLI but add interactive prompts. For example, `describe` shows the generated spec, then asks whether to create the source class. `run` displays results and offers to generate missing classes or methods.

### Smart Routing

When AI is configured, input that doesn't match a built-in command is sent to the AI assistant. PhpSpec also detects when you're addressing the AI even if the input starts with a command name:

```
> describe App/Calculator                       # runs describe command
> describe what the Loader class does            # routes to AI (too many words)
> run spec/App                                   # runs specs
> run my specs and explain the failures          # routes to AI
```

## AI Configuration

Add an `ai` section to your config file (`phpspec.yaml`, `phpspec.yml`, or `phpspec.json`):

```yaml
ai:
  provider: anthropic
  api_key: YOUR_API_KEY
```

Or with an explicit model:

```yaml
ai:
  provider: openai
  model: gpt-4o
  api_key: YOUR_API_KEY
```

| Key | Required | Description |
|---|---|---|
| `provider` | Yes | `google`, `anthropic`, or `openai` |
| `model` | No | Model identifier (see defaults below) |
| `api_key` | Yes | API key for the provider |

### Providers and Default Models

| Provider | Default Model | Auth |
|---|---|---|
| `google` | `gemini-2.5-pro` | API key |
| `anthropic` | `claude-sonnet-4-20250514` | API key |
| `openai` | `gpt-4o` | API key |

All three providers support tool calling, vision, and streaming.

## AI Assistant

When configured, the AI assistant acts as an agentic pair programmer. It maintains conversation history across the session, reads your project files for context, and uses tools to generate and run code.

### What You Can Ask

```
> write a spec for a UserRepository that finds users by email
> create a feature scenario for user registration
> add a greet method example to the Greeter spec
> run my specs and tell me what's failing
> explain how the Loader class works
> read src/App/Calculator.php and suggest improvements
```

### Available Tools

The AI assistant has access to these tools during a pair session:

| Tool | Description |
|---|---|
| `generate_spec` | Write a `.spec.php` file with full content |
| `generate_feature` | Write a Gherkin `.feature` file |
| `generate_steps` | Write a `.steps.php` step definitions file |
| `write_file` | Create a new file (class, interface, etc.) |
| `update_file` | Modify an existing file (shows a diff) |
| `run_specs` | Run specs and return the output |
| `read_file` | Read a project file for context |
| `list_files` | List directory contents |

The assistant automatically scans your project tree and existing step definitions so it can reuse patterns already in your codebase.

### Project Context

On each request, the AI receives:

- Your project's directory structure (src, spec, features)
- All existing step definition signatures from `.steps.php` files
- The PhpSpec DSL reference (describe/it/let/context/expect syntax)
- Available matchers and mocking syntax
- Feature and step definition conventions

This means the AI writes code that matches your project's style and reuses existing step definitions rather than inventing new ones.

### Conversation History

The AI maintains conversation history within a session. You can build on previous exchanges:

```
> write a spec for a Calculator
  (AI generates spec)

> now add a divide method that throws on division by zero
  (AI updates the spec, remembering the Calculator context)

> run the spec
  (AI runs specs and reports results)
```

### Logging

All AI interactions are logged to `.phpspec/pair.log` with timestamps, tool calls, and results. This is useful for debugging or reviewing what the assistant did.

## The `next` Command

When you're not sure what to work on next, ask PhpSpec:

```bash
bin/phpspec next
```

The command scans your project's source files, specs, and features, sends the context to the AI, and suggests the single most impactful next step. The suggestion follows the scenario-first workflow — it will recommend a feature scenario before a spec, and a spec before an implementation.

Example output:

```
  Analysing project...

  Write a feature scenario for user registration.

  Your project has a UserRepository class with a spec, but no feature
  scenario covering the registration flow. Adding a scenario first will
  drive the step definitions and any missing specs.
```

The `next` command requires AI configuration (the same `ai:` section in `phpspec.yaml`).

## The `refactor` Command

AI-powered, behaviour-preserving refactoring. The AI analyses your source code, applies a single baby-step refactoring, and verifies that specs still pass.

```bash
bin/phpspec refactor "App\Calculator"
bin/phpspec refactor "App\Calculator::sum"
bin/phpspec refactor "spec/App/Calculator.spec.php"
```

### Target Resolution

| Input | Source File | Spec File |
|---|---|---|
| `App\Calculator` | `src/App/Calculator.php` | `spec/App/Calculator.spec.php` |
| `App\Calculator::sum` | `src/App/Calculator.php` (focused on `sum`) | `spec/App/Calculator.spec.php` |
| `spec/App/Calculator.spec.php` | `src/App/Calculator.php` (inferred) | `spec/App/Calculator.spec.php` |

### How It Works

1. **Baseline check** -- Runs your specs first. If they fail, refactoring is refused (you can't preserve behaviour that's already broken).
2. **AI analysis** -- The LLM reads your source and spec files, identifies a single refactoring opportunity.
3. **Apply** -- Writes the refactored code.
4. **Verify** -- Runs specs again. If they pass, the refactoring is kept. If they fail, the original file is restored.
5. **Report** -- Shows the technique name, a description of the change, and a unified diff.

### Refactoring Techniques

The AI chooses from standard refactoring techniques:

- Extract Method
- Inline Variable / Inline Temp
- Rename (variable, method, class)
- Extract Class / Move Method
- Replace Conditional with Polymorphism
- Introduce Parameter Object
- Replace Magic Number with Constant
- Simplify Conditional
- Remove Dead Code
- And others as appropriate

If the code is already clean, the AI reports that no refactoring is needed.

### Example Output

```
Technique: Extract Method
Description: Extracted validation logic into a validateInput() method

   1   <?php
   2   namespace App;
   3 - class Calculator {
   3 + class Calculator {
   4       public function add(int $a, int $b): int {
   5 -         if ($a < 0 || $b < 0) {
   6 -             throw new \InvalidArgumentException('Negative');
   7 -         }
   5 +         $this->validateInput($a, $b);
              return $a + $b;
          }
  10 +
  11 +     private function validateInput(int $a, int $b): void {
  12 +         if ($a < 0 || $b < 0) {
  13 +             throw new \InvalidArgumentException('Negative');
  14 +         }
  15 +     }
      }

Specs still pass.
```

### Requirements

- AI must be configured in `phpspec.yaml` (same `ai:` section as pair mode)
- Both the source file and spec file must exist
- Baseline specs must pass before refactoring begins
