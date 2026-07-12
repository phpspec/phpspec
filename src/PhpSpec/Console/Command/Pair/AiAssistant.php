<?php

/*
 * This file is part of PhpSpec, A php toolset to drive emergent
 * design by specification.
 *
 * (c) Marcello Duarte <marcello.duarte@gmail.com>
 * (c) Konstantin Kudryashov <ever.zet@gmail.com>
 * (c) Ciaran McNulty <ciaran@ciaranmcnulty.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpSpec\Console\Command\Pair;

use PhpSpec\Ai\AiTools;
use PhpSpec\Ai\Contracts\ProviderInterface;
use PhpSpec\Ai\Contracts\ToolInterface;
use PhpSpec\Ai\Message;
use PhpSpec\Ai\SpecRunner;
use PhpSpec\Ai\Tool;
use PhpSpec\Ai\ToolCall;
use PhpSpec\CodeGeneration\SpecGenerator;
use PhpSpec\Configuration;
use PhpSpec\Extensions\ExtensionLoader;
use PhpSpec\Filesystem;
use PhpSpec\RealFilesystem;
use Throwable;

/**
 * @internal
 * Handles natural-language input in pair mode via an LLM with tool use.
 *
 * Maintains full conversation history across calls so the LLM remembers
 * prior context (files generated, specs run, questions asked).
 */
final class AiAssistant
{
    /** @var Message[] */
    private array $messages = [];

    /** @var array<string, ToolInterface> */
    private array $tools = [];

    private bool $initialised = false;

    private Filesystem $filesystem;

    private const MAX_TURNS = 50;

    private const WRITE_TOOLS = ['generate_spec', 'add_example', 'generate_feature', 'generate_steps', 'write_file', 'update_file'];

    private readonly Chooser $chooser;

    private readonly RoleState $roleState;

    /** Role-neutral guidance (DSL, tools, project conventions), cached once. */
    private string $baseGuidance = '';

    /** The scanned project context, cached once so /swap never re-scans. */
    private string $projectContext = '';

    /** Index of the system message in the history, so /swap can rebuild only it. */
    private int $systemIndex = 0;

    /** Whether the AI has written its one artifact this turn (while driving). */
    private bool $artifactWrittenThisHandle = false;

    /**
     * @param ProviderInterface $provider the AI provider for LLM interactions
     * @param Configuration $config the project configuration
     * @param PairOutput $output the pair-mode output helper
     * @param string|null $model the LLM model identifier, or null for the default
     * @param Filesystem|null $filesystem filesystem abstraction for testability
     * @param bool $interactive whether to prompt for user confirmation on write actions
     * @param ExtensionLoader|null $extensionLoader optional extension loader for custom tools
     * @param Chooser|null $chooser the interactive chooser; shared with the dispatcher
     *                              so "always" answers span the whole pair session
     * @param RoleState|null $roleState the current pairing role; shared with the
     *                                  dispatcher so a /swap is seen by both
     */
    public function __construct(
        private readonly ProviderInterface $provider,
        private readonly Configuration $config,
        private readonly PairOutput $output,
        private readonly ?string $model = null,
        ?Filesystem $filesystem = null,
        private readonly bool $interactive = true,
        private readonly ?ExtensionLoader $extensionLoader = null,
        ?Chooser $chooser = null,
        ?RoleState $roleState = null,
    ) {
        $this->filesystem = $filesystem ?? new RealFilesystem();
        $this->chooser = $chooser ?? new Chooser($output, $interactive);
        $this->roleState = $roleState ?? new RoleState();
    }

    /**
     * Sends natural-language input to the LLM and displays the response.
     * Conversation history is preserved between calls.
     */
    public function handle(string $input): void
    {
        $this->output->getOutput()->writeln('');
        $this->output->getOutput()->writeln('  <fg=gray>Thinking...</>');

        try {
            PairLogger::log('INPUT', $input);
            $this->ensureInitialised();
            $this->artifactWrittenThisHandle = false;
            $this->messages[] = Message::user($input);

            $text = $this->runLoop();

            if ($text !== '') {
                $this->output->getOutput()->writeln('');
                foreach (explode("\n", $text) as $line) {
                    $this->output->getOutput()->writeln("  $line");
                }
                $this->output->getOutput()->writeln('');
            }
        } catch (Throwable $e) {
            $this->output->error("AI error: {$e->getMessage()}");
        }
    }

    /**
     * Agentic loop: call provider, execute any tool calls, repeat until
     * the LLM returns a plain text response or max turns is reached.
     */
    private function runLoop(): string
    {
        $model = $this->model ?? 'gemini-2.5-pro';

        for ($turn = 0; $turn < self::MAX_TURNS; $turn++) {
            $response = $this->provider->chat($this->messages, [
                'model' => $model,
                'maxTokens' => 8192,
                'temperature' => 0.3,
                'tools' => $this->advertisedTools(),
            ]);

            $this->messages[] = Message::assistant(
                $response->text,
                $response->toolCalls ?: null,
            );

            if (!$response->hasToolCalls()) {
                PairLogger::log('RESPONSE', trim($response->text));
                return trim($response->text);
            }

            foreach ($response->toolCalls as $toolCall) {
                $result = $this->executeTool($toolCall);
                $this->messages[] = Message::toolResult($toolCall->id, $result);
            }
        }

        return 'Reached maximum tool turns. Please try a simpler request.';
    }

    /**
     * The tools advertised to the model this turn, serialised for the provider.
     *
     * When the AI is navigating (the human drives) the write tools are withheld
     * entirely — the role is enforced by not offering them, not merely by asking
     * the model not to use them.
     *
     * @return list<array{name: string, description: string, input_schema: array<string, mixed>}>
     */
    private function advertisedTools(): array
    {
        $tools = array_values($this->tools);
        $role = $this->roleState->current();

        // Withhold the write tools when the AI is navigating (it never writes),
        // and — while it drives — once it has already written its one artifact
        // this turn, so it can only read and run for the rest of the turn.
        $withholdWrites = $role->aiIsNavigator()
            || ($role->aiIsDriver() && $this->artifactWrittenThisHandle);

        if ($withholdWrites) {
            $tools = array_values(array_filter(
                $tools,
                fn(ToolInterface $tool) => !in_array($tool->getName(), self::WRITE_TOOLS, true),
            ));
        }

        return array_map(
            fn(ToolInterface $tool) => [
                'name' => $tool->getName(),
                'description' => $tool->getDescription(),
                'input_schema' => $tool->getParameterSchema(),
            ],
            $tools,
        );
    }

    private function executeTool(ToolCall $toolCall): mixed
    {
        $tool = $this->tools[$toolCall->name] ?? null;

        if ($tool === null) {
            return ['error' => "Unknown tool: $toolCall->name"];
        }

        $this->output->getOutput()->writeln($this->formatToolDisplay($toolCall));
        PairLogger::log('TOOL', "$toolCall->name(" . json_encode($toolCall->arguments) . ')');

        $isWrite = in_array($toolCall->name, self::WRITE_TOOLS, true);

        if ($isWrite) {
            $denial = $this->denyWrite();

            if ($denial !== null) {
                return $denial;
            }
        }

        try {
            $result = $tool->execute($toolCall->arguments);

            if ($isWrite) {
                $this->artifactWrittenThisHandle = true;
            }

            PairLogger::log('RESULT', is_string($result) ? $result : (json_encode($result) ?: ''));

            return $result;
        } catch (Throwable $e) {
            PairLogger::log('RESULT', "ERROR: {$e->getMessage()}");

            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Decides whether a write tool may run this turn. Returns an error payload
     * to send back to the model when the write is refused — the AI never writes
     * while navigating, only one artifact per turn while driving, and the user
     * can still decline — or null when the write may proceed.
     *
     * @return array{error: string}|null
     */
    private function denyWrite(): ?array
    {
        $role = $this->roleState->current();

        if ($role->aiIsNavigator()) {
            PairLogger::log('RESULT', 'Navigator refusal');

            return ['error' => 'I\'m navigating — you\'re driving, so I won\'t write files. Use the commands (describe, exemplify, run), or /swap to hand me the keyboard.'];
        }

        if ($role->aiIsDriver() && $this->artifactWrittenThisHandle) {
            PairLogger::log('RESULT', 'One artifact per turn');

            return ['error' => 'One artifact per turn while I drive. Let\'s run or inspect what we have, then continue on the next turn.'];
        }

        if (!$this->chooser->choose('Allow this action?', 'write-files', 'apply file changes')) {
            PairLogger::log('RESULT', 'User declined');

            return ['error' => 'User declined this action.'];
        }

        return null;
    }

    private function ensureInitialised(): void
    {
        if ($this->initialised) {
            return;
        }

        $this->initialised = true;

        foreach ($this->buildTools() as $tool) {
            $this->tools[$tool->getName()] = $tool;
        }

        $this->baseGuidance = $this->buildBaseGuidance();
        $this->projectContext = $this->buildProjectContext();

        $this->messages[] = $this->buildSystemMessage();
        $this->systemIndex = array_key_last($this->messages);
    }

    /**
     * Builds the system message for the current role: the role contract artifact
     * first, then the role-neutral guidance and the scanned project context.
     */
    private function buildSystemMessage(): Message
    {
        return Message::system(
            $this->loadRoleArtifact($this->roleState->current())
            . "\n\n" . $this->baseGuidance
            . "\n\n" . $this->projectContext,
        );
    }

    /**
     * Loads the role's prompt artifact (navigator.txt / driver.txt) from disk,
     * falling back to a short inline contract when it cannot be read (so a
     * mocked filesystem or odd packaging never yields a role-less prompt).
     */
    private function loadRoleArtifact(PairRole $role): string
    {
        $path = dirname(__DIR__, 3) . '/Ai/Prompts/' . $role->promptArtifact() . '.txt';
        $text = $this->filesystem->exists($path) ? $this->filesystem->read($path) : '';

        if (trim($text) === '') {
            return $role->aiIsNavigator()
                ? 'You are the NAVIGATOR. The human is driving. You never write files — you review and suggest; the human triggers generation with the commands.'
                : 'You are the DRIVER. The human is navigating. Execute exactly what they direct, one artifact per turn, then show the diff, run the spec, and hand back.';
        }

        return $text;
    }

    /**
     * Rebuilds only the system message for the current role after a /swap. The
     * cached base guidance and project context are reused, so swapping is cheap.
     */
    public function reloadPrompt(): void
    {
        if (!$this->initialised) {
            return;
        }

        $this->messages[$this->systemIndex] = $this->buildSystemMessage();
    }

    /**
     * @return ToolInterface[]
     */
    private function buildTools(): array
    {
        $tools = [
            $this->generateSpecTool(),
            $this->addExampleTool(),
            $this->generateFeatureTool(),
            $this->generateStepsTool(),
            $this->writeFileTool(),
            $this->updateFileTool(),
            $this->runSpecsTool(),
            $this->askUserTool(),
            AiTools::readFile($this->filesystem),
            AiTools::listFiles($this->filesystem),
        ];

        if ($this->extensionLoader !== null) {
            foreach ($this->extensionLoader->getToolProviders() as $provider) {
                foreach ($provider->getTools() as $tool) {
                    $tools[] = $tool;
                }
            }
        }

        return $tools;
    }

    private function askUserTool(): Tool
    {
        $chooser = $this->chooser;

        return Tool::make(
            name: 'ask_user',
            description: 'Ask the user a yes/no question before proceeding. Use this whenever you need '
                . 'a confirmation or decision from the user instead of asking in plain text. '
                . 'Returns "yes", "always" (yes now and for all similar future questions) or "no".',
            parameters: [
                'question' => [
                    'type' => 'string',
                    'description' => 'The question to show the user, e.g. "Do you want me to generate the method fooBar()?"',
                ],
                'action' => [
                    'type' => 'string',
                    'description' => 'Short verb phrase naming the action, completing the sentence "always ..." (e.g. "generate methods")',
                ],
            ],
            handler: function (array $args) use ($chooser) {
                $kind = 'ai-' . preg_replace('/[^a-z0-9]+/', '-', strtolower($args['action']));

                if (!$chooser->choose($args['question'], $kind, $args['action'])) {
                    return 'no';
                }

                return $chooser->hasAlways($kind) ? 'always' : 'yes';
            },
        );
    }

    /**
     * Writes a generated file, creating parent directories as needed, then
     * shows a diff when the file already existed or a full listing when it is
     * new. This keeps an overwrite of an existing spec/feature from being
     * rendered as an all-new file with every line marked added.
     *
     * @param Filesystem $filesystem the filesystem abstraction
     * @param PairOutput $output the pair-mode output helper
     * @param string $filePath the absolute path to write
     * @param string $content the file content
     */
    private static function writeGenerated(Filesystem $filesystem, PairOutput $output, string $filePath, string $content): void
    {
        $existed = $filesystem->exists($filePath);
        $oldContent = $existed ? $filesystem->read($filePath) : '';

        $dir = dirname($filePath);
        if (!$filesystem->exists($dir)) {
            $filesystem->mkdir($dir);
        }

        $filesystem->write($filePath, $content);

        if ($existed) {
            $output->fileDiff($filePath, $oldContent, $content);
        } else {
            $output->fileDisplay($filePath, $content, true);
        }
    }

    private function generateSpecTool(): Tool
    {
        $specPath = ltrim($this->config->getSpecPath(), './');
        $specSuffix = $this->config->getSpecSuffix();
        $filesystem = $this->filesystem;
        $output = $this->output;

        return Tool::make(
            name: 'generate_spec',
            description: 'Write a WHOLE spec file for a PHP class (new spec, or a full rewrite). Provide the full spec file content including <?php tag, use statements, describe() block, and all it() examples. To add ONE example to a spec that already exists, use add_example instead — do NOT regenerate the whole file.',
            parameters: [
                'class_name' => [
                    'type' => 'string',
                    'description' => 'Class path using forward slashes (e.g. "App/Calculator")',
                ],
                'spec_content' => [
                    'type' => 'string',
                    'description' => 'The complete PHP spec file content',
                ],
            ],
            handler: function (array $args) use ($specPath, $specSuffix, $filesystem, $output) {
                $classPath = $args['class_name'];
                $content = $args['spec_content'];

                $filePath = getcwd() . DIRECTORY_SEPARATOR
                    . $specPath . DIRECTORY_SEPARATOR
                    . str_replace('/', DIRECTORY_SEPARATOR, $classPath)
                    . $specSuffix;

                self::writeGenerated($filesystem, $output, $filePath, $content);

                return "Spec file written to $filePath";
            },
        );
    }

    private function addExampleTool(): Tool
    {
        $specPath = ltrim($this->config->getSpecPath(), './');
        $specSuffix = $this->config->getSpecSuffix();
        $filesystem = $this->filesystem;
        $output = $this->output;

        return Tool::make(
            name: 'add_example',
            description: 'Add ONE it() example for a method to an existing spec, appending it without rewriting the rest of the file. Idempotent: does nothing if that method is already exemplified. Prefer this over generate_spec whenever the spec already exists and you only need to add a single example.',
            parameters: [
                'class_name' => [
                    'type' => 'string',
                    'description' => 'Class path using forward slashes (e.g. "App/Calculator")',
                ],
                'method' => [
                    'type' => 'string',
                    'description' => 'The method name to add an example for (e.g. "add")',
                ],
            ],
            handler: function (array $args) use ($specPath, $specSuffix, $filesystem, $output) {
                $classPath = $args['class_name'];
                $method = $args['method'];

                $filePath = getcwd() . DIRECTORY_SEPARATOR
                    . $specPath . DIRECTORY_SEPARATOR
                    . str_replace('/', DIRECTORY_SEPARATOR, $classPath)
                    . $specSuffix;

                $generator = new SpecGenerator($specPath, $filesystem, $specSuffix);

                $existed = $filesystem->exists($filePath);
                if (!$existed) {
                    $generator->generate($classPath);
                }

                $before = $existed ? $filesystem->read($filePath) : '';
                $added = $generator->addExample($classPath, $method);

                if (!$added && $existed) {
                    $output->getOutput()->writeln(sprintf(
                        '  <fg=gray>An example for %s::%s already exists.</>',
                        str_replace('/', '\\', $classPath),
                        $method,
                    ));

                    return "Example for $classPath::$method already exists; no change made.";
                }

                $after = $filesystem->read($filePath);
                if ($existed) {
                    $output->fileDiff($filePath, $before, $after);
                } else {
                    $output->fileDisplay($filePath, $after, true);
                }

                return "Example for $classPath::$method added to $filePath.";
            },
        );
    }

    private function generateFeatureTool(): Tool
    {
        $filesystem = $this->filesystem;
        $output = $this->output;
        $featuresPath = $this->resolveFeaturePaths()['features'];

        return Tool::make(
            name: 'generate_feature',
            description: 'Write a Gherkin .feature file. Provide complete feature content with Feature:, Scenario:, Given/When/Then steps.',
            parameters: [
                'feature_name' => [
                    'type' => 'string',
                    'description' => 'Feature file name without extension (e.g. "user-registration")',
                ],
                'content' => [
                    'type' => 'string',
                    'description' => 'The complete Gherkin feature file content',
                ],
            ],
            handler: function (array $args) use ($filesystem, $output, $featuresPath) {
                $name = $args['feature_name'];
                $content = $args['content'];

                $filePath = getcwd() . '/' . $featuresPath . '/' . $name . '.feature';

                self::writeGenerated($filesystem, $output, $filePath, $content);

                return "Feature file written to $filePath";
            },
        );
    }

    private function generateStepsTool(): Tool
    {
        $filesystem = $this->filesystem;
        $output = $this->output;
        $stepsPath = $this->resolveFeaturePaths()['steps'];

        return Tool::make(
            name: 'generate_steps',
            description: 'Write a .steps.php file with step definitions for a feature. Uses bare given(), when(), then() global functions (NO use/import statements for them). Placeholders: {string}, {int}.',
            parameters: [
                'feature_name' => [
                    'type' => 'string',
                    'description' => 'Step file name without extension (e.g. "user-registration")',
                ],
                'content' => [
                    'type' => 'string',
                    'description' => 'The complete PHP step definitions file content',
                ],
            ],
            handler: function (array $args) use ($filesystem, $output, $stepsPath) {
                $name = $args['feature_name'];
                $content = $args['content'];

                $filePath = getcwd() . '/' . $stepsPath . '/' . $name . '.steps.php';

                self::writeGenerated($filesystem, $output, $filePath, $content);

                return "Steps file written to $filePath";
            },
        );
    }

    private function writeFileTool(): Tool
    {
        $filesystem = $this->filesystem;
        $output = $this->output;

        return Tool::make(
            name: 'write_file',
            description: 'Create a new file with the given content. Use this for any file type not covered by generate_spec/generate_feature/generate_steps.',
            parameters: [
                'path' => [
                    'type' => 'string',
                    'description' => 'Relative path from project root (e.g. "src/App/Service.php")',
                ],
                'content' => [
                    'type' => 'string',
                    'description' => 'The complete file content',
                ],
            ],
            handler: function (array $args) use ($filesystem, $output) {
                $path = $args['path'];
                $content = $args['content'];
                $absPath = getcwd() . '/' . ltrim($path, '/');

                if ($filesystem->exists($absPath)) {
                    return "File already exists: $path. Use update_file to modify it.";
                }

                $dir = dirname($absPath);
                if (!$filesystem->exists($dir)) {
                    $filesystem->mkdir($dir);
                }

                $filesystem->write($absPath, $content);
                $output->fileDisplay($absPath, $content, true);

                return "File written to $absPath";
            },
        );
    }

    private function updateFileTool(): Tool
    {
        $filesystem = $this->filesystem;
        $output = $this->output;

        return Tool::make(
            name: 'update_file',
            description: 'Update an existing file with new content. Use this to modify source files, config files, or any existing file.',
            parameters: [
                'path' => [
                    'type' => 'string',
                    'description' => 'Relative path from project root (e.g. "src/App/Service.php")',
                ],
                'content' => [
                    'type' => 'string',
                    'description' => 'The complete new file content',
                ],
            ],
            handler: function (array $args) use ($filesystem, $output) {
                $path = $args['path'];
                $content = $args['content'];
                $absPath = getcwd() . '/' . ltrim($path, '/');

                if (!$filesystem->exists($absPath)) {
                    return "File not found: $path. Use write_file to create it.";
                }

                $oldContent = $filesystem->read($absPath);
                $filesystem->write($absPath, $content);
                $output->fileDiff($absPath, $oldContent, $content);

                return "File updated: $absPath";
            },
        );
    }

    private function runSpecsTool(): Tool
    {
        $output = $this->output;

        return Tool::make(
            name: 'run_specs',
            description: 'Run phpspec specs via subprocess. Returns stdout and stderr so you can report results.',
            parameters: [
                'path' => [
                    'type' => 'string',
                    'description' => 'Optional path to run (e.g. "spec/App/Calculator.spec.php"). Leave empty to run all.',
                    'default' => '',
                ],
            ],
            handler: function (array $args) use ($output) {
                $path = $args['path'] ?? '';
                if ($path === '') {
                    $path = '.';
                }

                $output->getOutput()->writeln('  <fg=gray>Running specs...</>');

                [, $result] = SpecRunner::run($path);

                return $result ?: 'No output';
            },
        );
    }

    /**
     * Role-neutral guidance shared by both roles: the DSL, the tools, and the
     * project conventions. The role contract itself lives in the prompt artifacts.
     */
    private function buildBaseGuidance(): string
    {
        $specPath = ltrim($this->config->getSpecPath(), './');
        $srcPath = ltrim($this->config->getSrcPath(), './');
        $specSuffix = $this->config->getSpecSuffix();
        $featurePaths = $this->resolveFeaturePaths();
        $featuresPath = $featurePaths['features'];
        $stepsPath = $featurePaths['steps'];

        return <<<PROMPT
        You are an AI assistant for PhpSpec, a specification-driven BDD framework for PHP.
        You help developers write specs, features, step definitions, and understand their codebase.

        ## Project Layout
        - Spec files: $specPath/ (suffix: $specSuffix)
        - Source files: $srcPath/
        - Feature files: $featuresPath/
        - Step definitions: $stepsPath/

        ## PhpSpec DSL
        Spec files use a Jasmine/RSpec-style syntax:

        ```php
        <?php

        use App\Calculator;

        describe(Calculator::class, function () {
            let('calculator', fn() => new Calculator());

            it('adds two numbers', function () {
                expect(\$this->calculator->add(2, 3))->toBe(5);
            });

            it('subtracts two numbers', function () {
                expect(\$this->calculator->subtract(5, 3))->toBe(2);
            });

            context('with negative numbers', function () {
                it('adds negative numbers', function () {
                    expect(\$this->calculator->add(-1, -2))->toBe(-3);
                });
            });
        });
        ```

        IMPORTANT: describe(), it(), let(), context(), expect() are GLOBAL functions.
        Do NOT add "use function" imports for them in spec files. Only `use` the class under test.

        ### Available Matchers
        toBe, toBeLike, toHaveCount, toBeAnInstanceOf, toBeTrue, toBeFalse, toBeNull,
        toBeEmpty, toContain, toMatch, toBeGreaterThan, toBeLessThan, toHaveKey,
        toStartWith, toEndWith, toHaveProperty, toBeCallable, toBeOfType, toThrow

        ### Negation
        `expect(\$x)->not()->toBe(\$y)`

        ### Mocks
        Type-hinted parameters in it() closures are auto-mocked:
        ```php
        it('uses a collaborator', function (Logger \$logger) {
            allow(\$logger->log())->toReturn(true);
            // ... use \$logger
            expect(\$logger->log())->toBeCalled();
        });
        ```

        ### Pending/Focused/Skipped
        - `xit()`, `xdescribe()`, `xcontext()` — pending (skipped)
        - `fit()`, `fdescribe()`, `fcontext()` — focused (only these run)
        - `pending()` / `skip()` — skip from inside an example

        ## Gherkin Features
        Feature files use standard Gherkin:
        ```gherkin
        Feature: Calculator
          Scenario: Adding numbers
            Given I have a calculator
            When I add 2 and 3
            Then the result should be 5
        ```

        ## Step Definitions
        Step files define handlers for Gherkin steps.
        IMPORTANT: given(), when(), then() are GLOBAL functions auto-loaded by PhpSpec.
        Do NOT add "use function" imports for them — they are NOT namespaced.
        The only <?php tag at the top is required, but NO use/import statements for step functions.

        ```php
        <?php

        given('I have a calculator', function () {
            \$this->calculator = new App\Calculator();
        });

        when('I add {int} and {int}', function (int \$a, int \$b) {
            \$this->result = \$this->calculator->add(\$a, \$b);
        });

        then('the result should be {int}', function (int \$expected) {
            expect(\$this->result)->toBe(\$expected);
        });
        ```

        Placeholders: `{string}`, `{int}`, `{float}` are auto-captured.

        NEVER include any of these in step files:
        - `use function PhpSpec\StoryBDD\given;`
        - `use function PhpSpec\StoryBDD\when;`
        - `use function PhpSpec\StoryBDD\then;`
        - `use PhpSpec\StoryBDD\...;`
        These will cause fatal errors. The functions are globally available.

        ## Guidelines
        - You are working on THIS project. The project file tree is provided below.
        - ALWAYS use `read_file` to inspect existing source code and specs BEFORE generating new files.
          Look at the actual classes, method signatures, and existing spec patterns in the project.
        - ALWAYS use `list_files` first if you need to discover what files exist in a directory.
        - When generating specs, always include the `<?php` tag and appropriate `use` statements.
        - Use `let()` to set up shared state, `it()` for individual examples.
        - Use `context()` to group related examples.
        - Generate meaningful, descriptive example names.
        - To add a SINGLE example for a method to a spec that ALREADY exists, use `add_example`
          (it appends just that one it() and is idempotent). Do NOT call `generate_spec` to add
          one example — regenerating the whole file re-marks every line as new and risks
          duplicating existing examples. Reserve `generate_spec` for a brand-new spec or a
          deliberate full rewrite.
        - Match the coding style and patterns of existing specs and step definitions in the project.
        - When asked to run specs, use the `run_specs` tool and report the results clearly.
        - Keep responses concise. Show what you did, not long explanations of what you will do.
        - Use `write_file` to create any new file not covered by the generate_* tools.
        - Use `update_file` to modify existing files. Always `read_file` first to see the current content.

        ## MANDATORY: Read before generating (applies to ALL generation tasks)
        Before generating ANY `.feature`, `.steps.php`, or `.spec.php` file you MUST first:
        1. `read_file` at least one existing file of the same type to learn the project conventions.
        2. For features: read at least one existing step file to learn what step patterns exist.
           Your Gherkin steps MUST use the exact patterns from existing step definitions listed in
           "Existing step definitions" below. Do NOT invent step patterns like `Given a class` if
           the existing steps use `Given a source file`. If you need a step that doesn't exist,
           generate a new `.steps.php` file for it.
        3. For specs: read at least one existing spec to see the DSL style used in THIS project.
           This project uses describe/it/expect syntax — NOT ObjectBehavior/shouldReturn/willReturn.

        ## Feature scenario rules
        - BEFORE generating a `.feature` file, you MUST first read the "Existing step definitions"
          section below AND `read_file` at least one existing step file to learn the exact step
          patterns available. Every Given/When/Then line in your feature MUST match an existing
          step definition or be a new step you will define in a new `.steps.php` file.
          NEVER invent step patterns — if you are unsure what steps exist, use `list_files` and
          `read_file` on the steps directory first.
        - Feature scenarios run in temporary project directories created by the test harness.
          NEVER use `write_file` or `update_file` to create source files, spec files, or config
          files in the actual project for scenario support. Instead, define them inline in the
          `.feature` file using Gherkin DocString blocks with the step patterns from existing
          step definitions (e.g. `Given a source file`, `Given a spec file`, `Given a class`).
        - Only use `write_file` for files the user explicitly asks to create in their project.
        - NEVER modify existing shared step files (like `runner.steps.php`, `setup.steps.php`).
          Create new `.steps.php` files for new step definitions instead.

        ## Step definition quality rules
        - BEFORE generating step files, ALWAYS `read_file` at least one existing step file to learn
          the project's conventions (how state is set up, how commands are executed, what `\$this`
          properties are available on the world object, etc.).
        - Reuse existing step definitions. The "Existing step definitions" section below lists all
          steps already defined in this project. Write feature scenarios that compose these existing
          steps whenever possible — only define new steps when no existing step covers the need.
        - Steps MUST execute real code. NEVER hardcode, stub, or simulate output in a step body.
          Every step must interact with real files, real objects, or real commands.
        - Steps MUST NOT assert against values they set themselves. If a `when` step sets
          `\$this->output`, the value must come from actually running something, not from a string
          literal written in the step body.
        - When a feature needs behaviour that doesn't exist yet, the steps should exercise the
          real (not-yet-implemented) code path so the scenario fails for the right reason —
          a missing class, a missing method, or wrong output — not because of a placeholder.
        PROMPT;
    }

    /**
     * Scans project directories to build a file tree for the system prompt.
     */
    private function buildProjectContext(): string
    {
        $sections = [];

        $specPath = ltrim($this->config->getSpecPath(), './');
        $srcPath = ltrim($this->config->getSrcPath(), './');

        $srcTree = $this->scanTree(getcwd() . '/' . $srcPath, 3);
        if ($srcTree !== '') {
            $sections[] = "## Source files ($srcPath/)\n$srcTree";
        }

        $specTree = $this->scanTree(getcwd() . '/' . $specPath, 3);
        if ($specTree !== '') {
            $sections[] = "## Spec files ($specPath/)\n$specTree";
        }

        $featuresDir = getcwd() . '/features';
        if ($this->filesystem->exists($featuresDir) && $this->filesystem->isDir($featuresDir)) {
            $featTree = $this->scanTree($featuresDir, 3);
            if ($featTree !== '') {
                $sections[] = "## Feature files\n$featTree";
            }
        }

        $stepsDir = $this->resolveFeaturePaths()['steps'];
        $stepSignatures = $this->scanStepSignatures(getcwd() . '/' . $stepsDir);
        if ($stepSignatures !== '') {
            $sections[] = "## Existing step definitions\nThese steps are already defined — reuse them in new scenarios:\n$stepSignatures";
        }

        if (empty($sections)) {
            return '';
        }

        return "# Project file tree\n\n" . implode("\n\n", $sections);
    }

    /**
     * Recursively scans a directory tree up to a given depth, returning an indented listing.
     */
    private function scanTree(string $absPath, int $maxDepth, int $depth = 0): string
    {
        if ($depth >= $maxDepth || !$this->filesystem->exists($absPath) || !$this->filesystem->isDir($absPath)) {
            return '';
        }

        $entries = $this->filesystem->scandir($absPath);
        $entries = array_filter($entries, fn($e) => $e !== '.' && $e !== '..');
        sort($entries);

        $lines = [];
        $indent = str_repeat('  ', $depth);

        foreach ($entries as $entry) {
            $full = $absPath . '/' . $entry;
            if ($this->filesystem->isDir($full)) {
                $lines[] = "$indent$entry/";
                $sub = $this->scanTree($full, $maxDepth, $depth + 1);
                if ($sub !== '') {
                    $lines[] = $sub;
                }
            } else {
                $lines[] = "$indent$entry";
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Scans step definition files and extracts their signatures
     * (e.g. given('a spec file {string}:'), when('I run phpspec run'), etc.)
     * so the AI knows what steps are already available for reuse.
     */
    private function scanStepSignatures(string $stepsDir): string
    {
        if (!$this->filesystem->exists($stepsDir) || !$this->filesystem->isDir($stepsDir)) {
            return '';
        }

        $entries = $this->filesystem->scandir($stepsDir);
        $lines = [];

        foreach ($entries as $entry) {
            if (!str_ends_with($entry, '.steps.php')) {
                continue;
            }

            $content = $this->filesystem->read($stepsDir . '/' . $entry);
            if (preg_match_all('/\b(given|when|then)\s*\(\s*[\'"](.+?)[\'"]\s*,/i', $content, $matches)) {
                $lines[] = "# $entry";
                foreach ($matches[1] as $i => $keyword) {
                    $lines[] = "$keyword('{$matches[2][$i]}')";
                }
                $lines[] = '';
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Resolves the features and steps directories from the project layout.
     * Scans the features directory for a `steps/` subdirectory and a `scenarios/`
     * subdirectory (or uses `features/` directly for feature files).
     *
     * @return array{features: string, steps: string} relative paths
     */
    private function resolveFeaturePaths(): array
    {
        $base = getcwd() . '/features';

        $featuresPath = 'features/scenarios';
        $stepsPath = 'features/steps';

        if ($this->filesystem->exists($base) && $this->filesystem->isDir($base)) {
            $entries = $this->filesystem->scandir($base);

            if (!in_array('scenarios', $entries)) {
                $featuresPath = 'features';
            }

            if (!in_array('steps', $entries)) {
                // Look for a steps/ dir inside scenarios/
                $scenariosSteps = $base . '/scenarios/steps';
                if ($this->filesystem->exists($scenariosSteps) && $this->filesystem->isDir($scenariosSteps)) {
                    $stepsPath = 'features/scenarios/steps';
                }
            }
        }

        return ['features' => $featuresPath, 'steps' => $stepsPath];
    }

    private function formatToolDisplay(ToolCall $toolCall): string
    {
        $names = [
            'read_file' => ['Read', 'path'],
            'list_files' => ['List', 'directory'],
            'write_file' => ['Write', 'path'],
            'update_file' => ['Update', 'path'],
            'generate_spec' => ['Generate spec', 'class_name'],
            'add_example' => ['Add example', 'class_name'],
            'generate_feature' => ['Generate feature', 'feature_name'],
            'generate_steps' => ['Generate steps', 'feature_name'],
            'run_specs' => ['Run', 'path'],
        ];

        $info = $names[$toolCall->name] ?? null;
        if ($info === null) {
            return "  \u{23FA} <options=bold>$toolCall->name</>";
        }

        [$displayName, $argKey] = $info;
        $mainArg = $toolCall->arguments[$argKey] ?? '';
        if ($mainArg === '' && $toolCall->name === 'run_specs') {
            $mainArg = 'all';
        }

        $argDisplay = $mainArg !== '' ? "($mainArg)" : '';
        return "  \u{23FA} <options=bold>$displayName</>$argDisplay";
    }

}
