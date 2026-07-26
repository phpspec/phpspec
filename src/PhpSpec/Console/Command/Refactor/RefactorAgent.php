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

namespace PhpSpec\Console\Command\Refactor;

use PhpSpec\Ai\Agent\CommandProfile;
use PhpSpec\Ai\AiTools;
use PhpSpec\Ai\Contracts\ProviderInterface;
use PhpSpec\Ai\Contracts\ToolInterface;
use PhpSpec\Ai\Message;
use PhpSpec\Ai\ProviderFactory;
use PhpSpec\Ai\SpecRunner;
use PhpSpec\Ai\Tool;
use PhpSpec\Ai\ToolCall;
use PhpSpec\Filesystem;
use PhpSpec\RealFilesystem;
use RuntimeException;
use Throwable;

/**
 * @internal
 * AI agent that performs safe, single-step refactorings on PHP classes.
 *
 * Uses an LLM with tools to read source/spec files, apply a refactoring,
 * and verify specs still pass. Reverts on failure.
 */
final class RefactorAgent
{
    /** @var Message[] */
    private array $messages = [];

    /** @var array<string, ToolInterface> */
    private array $tools = [];

    private bool $initialised = false;

    private Filesystem $filesystem;

    private ?RefactorResult $result = null;

    private const MAX_TURNS = 10;

    /**
     * @param ProviderInterface $provider the AI provider for LLM interactions
     * @param string|null $model the LLM model identifier, or null for the default
     * @param Filesystem|null $filesystem filesystem abstraction for testability
     * @param string|null $effort the configured reasoning effort, passed through to the provider
     */
    public function __construct(
        private readonly ProviderInterface $provider,
        private readonly ?string $model = null,
        ?Filesystem $filesystem = null,
        private readonly ?string $effort = null,
    ) {
        $this->filesystem = $filesystem ?? new RealFilesystem();
    }

    /**
     * Performs a single baby-step refactoring on the given source file.
     *
     * @param string $srcPath absolute path to the source file
     * @param string $specPath absolute path to the spec file
     * @param string|null $method optional method to focus refactoring on
     */
    public function refactor(string $srcPath, string $specPath, ?string $method): RefactorResult
    {
        $this->ensureInitialised($srcPath, $specPath);

        $sourceContent = $this->filesystem->read($srcPath);
        $specContent = $this->filesystem->read($specPath);

        $focus = $method !== null ? "\n\nFocus your refactoring on the `$method()` method only." : '';

        $this->messages[] = Message::user(
            "Refactor this class. Here is the source file ($srcPath):\n\n```php\n$sourceContent\n```\n\n"
            . "Here is the spec file ($specPath):\n\n```php\n$specContent\n```$focus",
        );

        $this->runLoop();

        return $this->result ?? new RefactorResult(
            success: false,
            technique: 'None',
            description: 'The AI did not apply any refactoring.',
            diff: '',
            specOutput: '',
        );
    }

    /**
     * Agentic loop: call provider, execute tool calls, repeat until
     * the LLM returns a plain text response or max turns is reached.
     */
    private function runLoop(): void
    {
        $profile = $this->profile();
        $options = [
            'model' => $this->model ?? ProviderFactory::defaultModel('google'),
            'maxTokens' => $profile->maxTokens ?? 8192,
            'temperature' => $profile->temperature ?? 0.3,
        ];
        if ($this->effort !== null) {
            $options['effort'] = $this->effort;
        }

        for ($turn = 0; $turn < self::MAX_TURNS; $turn++) {
            $response = $this->provider->chat($this->messages, $options + [
                'tools' => array_map(
                    fn(ToolInterface $tool) => [
                        'name' => $tool->getName(),
                        'description' => $tool->getDescription(),
                        'input_schema' => $tool->getParameterSchema(),
                    ],
                    array_values($this->tools),
                ),
            ]);

            $this->messages[] = Message::assistant(
                $response->text,
                $response->toolCalls ?: null,
            );

            if (!$response->hasToolCalls()) {
                return;
            }

            foreach ($response->toolCalls as $toolCall) {
                $result = $this->executeTool($toolCall);
                $this->messages[] = Message::toolResult($toolCall->id, $result);
            }
        }
    }

    private function executeTool(ToolCall $toolCall): mixed
    {
        $tool = $this->tools[$toolCall->name] ?? null;

        if ($tool === null) {
            return ['error' => "Unknown tool: $toolCall->name"];
        }

        try {
            return $tool->execute($toolCall->arguments);
        } catch (Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    private function ensureInitialised(string $srcPath, string $specPath): void
    {
        if ($this->initialised) {
            return;
        }

        $this->initialised = true;

        foreach ($this->buildTools($srcPath, $specPath) as $tool) {
            $this->tools[$tool->getName()] = $tool;
        }

        $this->messages[] = Message::system($this->buildSystemPrompt());
    }

    /**
     * @return Tool[]
     */
    private function buildTools(string $srcPath, string $specPath): array
    {
        return [
            AiTools::readFile($this->filesystem),
            AiTools::listFiles($this->filesystem),
            $this->applyRefactoringTool($srcPath, $specPath),
        ];
    }

    private function applyRefactoringTool(string $srcPath, string $specPath): Tool
    {
        return Tool::make(
            name: 'apply_refactoring',
            description: 'Apply a refactoring to the source file. Automatically runs specs and reverts on failure. Call this exactly once with your proposed refactoring.',
            parameters: [
                'content' => [
                    'type' => 'string',
                    'description' => 'The complete new file content after refactoring',
                ],
                'technique' => [
                    'type' => 'string',
                    'description' => 'The name of the refactoring technique (e.g. "Extract Method", "Inline Variable")',
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'A brief description of what was changed and why',
                ],
            ],
            handler: function (array $args) use ($srcPath, $specPath) {
                $newContent = $args['content'];
                $technique = $args['technique'];
                $description = $args['description'];

                // Backup original
                $original = $this->filesystem->read($srcPath);

                // Compute diff
                $oldLines = explode("\n", $original);
                $newLines = explode("\n", $newContent);
                $diffEntries = Diff::compute($oldLines, $newLines);
                $diffText = Diff::format($diffEntries);

                // Write new content
                $this->filesystem->write($srcPath, $newContent);

                // Run specs
                [$exitCode, $output] = self::runSpecs($specPath);

                if ($exitCode !== 0) {
                    // Revert
                    $this->filesystem->write($srcPath, $original);

                    $this->result = new RefactorResult(
                        success: false,
                        technique: $technique,
                        description: $description,
                        diff: $diffText,
                        specOutput: $output,
                    );

                    return "Refactoring failed — specs did not pass. Reverted.\n\nSpec output:\n$output";
                }

                $this->result = new RefactorResult(
                    success: true,
                    technique: $technique,
                    description: $description,
                    diff: $diffText,
                    specOutput: $output,
                );

                return 'Refactoring applied successfully. Specs pass.';
            },
        );
    }

    /**
     * @return array{0: int, 1: string} [exitCode, output]
     */
    private static function runSpecs(string $specPath): array
    {
        return SpecRunner::run($specPath);
    }

    /**
     * The refactor prompt comes from the command's manifest file
     * (`Ai/Prompts/commands/refactor.txt`), so tuning the refactoring rules is
     * a text edit; a short inline contract covers odd packaging.
     */
    private function buildSystemPrompt(): string
    {
        $body = $this->profile()->body ?? '';
        if (trim($body) !== '') {
            return $body;
        }

        return 'You are a PHP refactoring assistant for PhpSpec. Propose exactly ONE behaviour-preserving, named refactoring and apply it with the apply_refactoring tool; specs must pass before and after.';
    }

    /**
     * The refactor command's manifest, or null when its prompt file cannot be
     * loaded (prompts are shipped package code, so they load from the real
     * filesystem).
     */
    private function profile(): ?CommandProfile
    {
        try {
            return CommandProfile::load('refactor');
        } catch (RuntimeException) {
            return null;
        }
    }
}
