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

### Session greeting

Pair mode opens by looking at your project once and greeting you with what matters right now, rather than a static menu:

- **Red suite** — it names the failing subject and its example, and offers to start there.
- **Green with a pending example** — it names the nearest `xit()` gap to make real.
- **Green and clean** — a short observation, then it's your call.
- **Empty project** — an invitation to write the first spec.

The greeting adapts to your configuration: with an AI provider configured it invites plain English; without one it points you at the deterministic commands (`/describe`, `/exemplify`, `/run`). The full command list is always available behind `/help`.

### Driving and navigating

Pair mode is a pair, not a code agent: you share one keyboard and work in turns. Nobody hand-types code any more — the generators are the keyboard, and *driving* means deciding what gets generated and pulling the trigger. `/swap` changes who holds it (this needs an AI provider):

- **You drive, the AI navigates** (the default). The AI reviews and suggests one step ahead (intent, then location, then the exact line only when you ask), but it never writes files unbidden. When its advice becomes concrete it **offers** the change instead of dictating code: you see the exact diff and accept or decline through the numbered chooser; an accepted offer lands through the same write gate as every other change and auto-verifies. One offer per turn, and a declined offer is never re-offered.
- **The AI drives, you navigate** (after `/swap`). You give the intent; the AI makes it real one artifact at a time, shows the diff, runs the spec, and hands back.

`/help` shows the current contract. Swap back at any time with another `/swap`.

### Built-in Commands

These commands work without AI configuration, and each works with or without its leading slash:

| Command | Description |
|---|---|
| `/describe <Class>` | Generate a spec file and optionally create the class |
| `/exemplify <Class> <method>` | Add a method example to an existing spec |
| `/run [path\|keyword] [options]` | Run specs (or features) and offer code generation for failures |
| `/next` | Suggest the next step from the real suite state |
| `/generate <instruction>` | Turn plain English into a spec example or code — diff, then confirm (requires AI) |
| `/clear` | Clear the terminal |
| `/swap` | Swap who drives (needs an AI provider) |
| `/help` | Show available commands and AI status |
| `/quit`, `/exit` | Exit pair mode |

Commands mirror the CLI but add interactive prompts. For example, `/describe` shows the generated spec, then asks whether to create the source class. `/run` displays results and offers to generate missing classes or methods. Run options pass straight through to the runner: `/run --all`, `/run spec/App --stop-on-failure`.

### Command or prompt: how a line is read

A leading slash always marks a command. A bare line routes as a command too when its first word unambiguously reads as one, so the conversation flows without syntax in the way:

- `run` routes when everything after it is a path, a spec or feature file, a suite keyword, or an option: `run`, `run features`, `run --all`, `run spec/App`. Suite keywords translate to their options: `features`/`stories` mean `--story`, `all`/`everything` means `--all`, `specs` is the default run.
- `describe`, `exemplify`, and `refactor` route when the first argument looks like a class: `describe App/Basket`, `exemplify App\Calculator add`, `refactor Todo`.
- `generate` always routes: plain English is its argument.
- `next`, `help`, `swap`, `clear`, `quit`, and `exit` route only as the bare word.

Anything else is a prompt for the AI, and the guards keep conversation as conversation: "run me through the design", "help me understand this project", "next time we should tidy up" all reach the assistant, never a command. With no working AI your sentence isn't called an "unknown command": pair mode explains that natural language needs a provider.

```
> /describe App/Calculator                      # runs the describe command
> describe App/Calculator                       # the same command, no slash needed
> run features                                  # a story-only run (--story)
> run --all                                     # specs and features together
> describe what the Loader class does           # prose → routes to the AI
> run my specs and explain the failures         # prose → routes to the AI
```

### Suggested next command (ghost text)

After each command the prompt is pre-filled with a dim **ghost** of the natural next
step — describe a spec and it suggests running it, run and it suggests `/next`, and so on:

```
> /describe App/Todo
  ...
> /run spec/App/Todo.spec.php          ← the greyed-out suggestion, cursor before the /
```

Press **Right-arrow** or **Tab** to accept it (it turns full colour, cursor to the end),
or just start typing to dismiss it. **Up/Down** walk your history. On a terminal that
can't enter raw mode (or piped input) the prompt falls back to a plain line reader.

After `/next` with an AI provider, the ghost is built from the suggestion the AI
registers: a matching `/generate ...` pre-filled in the prompt, so acting on the
advice is Tab and Enter, never retyping it.

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
  model: gpt-5.1
  api_key: YOUR_API_KEY
```

| Key | Required | Description |
|---|---|---|
| `provider` | Yes | `google`, `anthropic`, `openai`, `grok`, `deepseek`, or `ollama` |
| `model` | No | Model identifier (see defaults below) |
| `api_key` | Yes (hosted providers) | API key for the provider |
| `max_tokens` | No | Output-token ceiling per reply |
| `effort` | No | Reasoning effort forwarded to the provider (where supported) |

Your `model` and `max_tokens` always beat the shipped defaults, and the pair
status bar shows the resolved model next to the provider.

### Providers and Default Models

| Provider | Default Model | Auth |
|---|---|---|
| `google` | `gemini-3.1-pro-preview` | API key |
| `anthropic` | `claude-sonnet-5` | API key |
| `openai` | `gpt-5.1` | API key |
| `grok` | `grok-4` | API key |
| `deepseek` | `deepseek-chat` | API key |
| `ollama` | `llama3.1` | local, no key |

The hosted providers support tool calling, vision, and streaming.

Each provider needs its PapiAI package installed alongside `papi-ai/papi-core`:

```bash
composer require papi-ai/papi-core papi-ai/google      # for Gemini
composer require papi-ai/papi-core papi-ai/anthropic   # for Claude
composer require papi-ai/papi-core papi-ai/openai       # for GPT
```

The status bar reflects whether the provider actually started, not merely that an `ai:`
section exists. If a provider is configured but can't start — its package isn't installed,
the name is misspelt, the key is for a different provider — the bar shows **`ai: unavailable`**
rather than `ai: on`, and the reason is shown the moment you type a prompt or run `/help`.
So an `AIza…` (Google) key under `provider: openai` will read as unavailable until you set
`provider: google`.

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
| `describe` | Start a spec: writes an empty `describe()` skeleton for a class (idempotent) |
| `add_example` | Add one `it()` example to a spec, one at a time (idempotent; never overwrites existing examples) |
| `generate_feature` | Write a Gherkin `.feature` file |
| `generate_steps` | Write a `.steps.php` step definitions file |
| `write_file` | Create a new file (class, interface, etc.) |
| `update_file` | Modify an existing file (shows a diff) |
| `inspect_symbol` | Inspect a class or method's signature for context |
| `run_specs` | Run specs and return the output |
| `ask_user` | Ask you a yes/no question through the numbered chooser |
| `read_file` | Read a project file for context |
| `list_files` | List directory contents |
| `offer_change` | Navigator only: offer one concrete file change, shown to you as a diff to accept or decline |
| `suggest_next` | Register the next-step suggestion that pre-fills the prompt after `/next` |

Specs are never written whole-file: the assistant starts one with `describe`
and grows it one example at a time with `add_example`, so your existing examples
are never overwritten.

The assistant automatically scans your project tree and existing step definitions so it can reuse patterns already in your codebase.

### Interactive Questions

Every question in a pair session -- whether asked by PhpSpec itself (creating
a class, running specs) or by the AI via `ask_user` -- uses the same numbered
chooser:

```
  Do you want me to create class App\Checkout for you?
   ▸ 1. Yes
     2. Yes, and don't ask again -- always create classes
     3. No
```

A single keypress decides: `1`/`2`/`3`, or the shortcuts `y` (yes), `a`
(always) and `n` or `Esc` (no) -- no Enter needed. The arrow keys move the
highlight and Enter confirms it. When input is piped rather than a terminal,
the chooser reads one answer line instead (digits or y/a/n; empty defaults to
yes), so scripted sessions keep working.

Option 2 is remembered per question kind for the rest of the session --
answer "always" to class creation and PhpSpec stops asking about classes,
while other questions (running specs, applying AI file changes) still prompt.
Nothing is persisted between sessions.

Press **Tab** on Yes or No to annotate the answer before giving it: the
highlighted line becomes an editable trailer.

```
  Apply this offered change?
   ▸ 1. Yes, rename the method to tasks▮
     2. Yes, and don't ask again -- always apply offered changes
     3. No
```

Type the note, backspace to edit, Enter to answer with it, Esc to drop it.
The note travels with the answer: accept an AI offer with "Yes, rename the
method to tasks" and the model reads exactly that amendment; decline with
"No, make it a Feature instead" and that becomes the direction it takes next,
instead of guessing why you said no. Piped input carries a note after a comma
(an answer line of "3, make it a Feature instead").

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

All AI interactions are logged to `.phpspec/pair/session.log` with timestamps, tool calls, and results. This is useful for debugging or reviewing what the assistant did.

## The `next` Command

When you're not sure what to work on next, ask PhpSpec:

```bash
bin/phpspec next
```

The command coaches the single next step in **outside-in, story-first TDD**, favouring feature (story) tests. When feature files are present it runs the whole suite (`--all`) and reads the real red/green state: a red or unwritten scenario drives the inner spec cycle; when the features are green it offers one baby step over the files you last touched — refactor the last code, add a new scenario, or start a new feature. The shared coaching lives in `src/PhpSpec/Ai/Prompts/next.txt`. With no feature files it falls back to the spec cycle (describe the missing class, or make the nearest pending example real).

Example output:

```
  Analysing project...

  Write a feature scenario for user registration.

  Your project has a UserRepository class with a spec, but no feature
  scenario covering the registration flow. Adding a scenario first will
  drive the step definitions and any missing specs.
```

The standalone `next` command requires AI configuration (the same `ai:` section in `phpspec.yaml`). If it suggests a class whose spec already exists, it will not send you round in circles describing it again — it points you at `bin/phpspec run` to drive out the missing class.

Inside pair mode, `/next` reads the real suite state rather than guessing, and follows the same outside-in, feature-first logic — a red scenario drops into the inner cycle, undefined steps get written, and green features prompt a baby step over the last-touched feature and source. **This works even without an AI provider**: the deterministic narrator gives the advice, and `next.txt` enriches it when a provider is configured (the navigator advises, the driver takes the step).

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
