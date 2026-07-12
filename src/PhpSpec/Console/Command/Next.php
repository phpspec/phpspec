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

namespace PhpSpec\Console\Command;

use PhpSpec\Ai\Message;
use PhpSpec\Ai\ProviderFactory;
use PhpSpec\Configuration;
use PhpSpec\Filesystem;
use PhpSpec\RealFilesystem;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface as Input;
use Symfony\Component\Console\Output\OutputInterface as Output;
use Symfony\Component\Console\Question\ConfirmationQuestion;

/**
 * @internal
 * CLI command that uses AI to suggest what to describe (spec or feature) next.
 *
 * Scans the project's source, spec, and feature files, sends the project
 * context to the LLM, and displays a suggestion for the next BDD step.
 * Offers to create the suggested artifact.
 */
final class Next extends Command
{
    private const CACHE_TTL = 30;

    private Filesystem $filesystem;

    /** @var (callable(array{provider: string, model?: string, api_key: string}): array{type: string, target: string, reason: string})|null */
    private $suggestFn;

    /**
     * @param Configuration $config
     * @param Filesystem|null $filesystem
     * @param (callable(array{provider: string, model?: string, api_key: string}): array{type: string, target: string, reason: string})|null $suggestFn
     */
    public function __construct(
        private readonly Configuration $config,
        ?Filesystem $filesystem = null,
        ?callable $suggestFn = null,
    ) {
        $this->filesystem = $filesystem ?? new RealFilesystem();
        $this->suggestFn = $suggestFn;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('next')
            ->setDescription('AI suggests what to describe or specify next');
    }

    protected function execute(Input $input, Output $output): int
    {
        // 1. Check AI config
        $aiConfig = $this->config->getAiConfig();
        if ($aiConfig === null) {
            $output->writeln('<fg=red>AI configuration required. Add an "ai" section to your phpspec config.</>');
            return 1;
        }

        // 2. Load bootstrap
        if (!$this->loadBootstrap()) {
            $output->writeln('<fg=red>Bootstrap file not found.</>');
            return 1;
        }

        // 3. Scan project and get suggestion
        $output->writeln('');
        $output->writeln('  <fg=gray>Analysing project...</>');
        $output->writeln('');

        $suggestion = $this->getSuggestion($aiConfig);

        // 4. Validate — if we got no target and no reason, the response was unusable
        if ($suggestion['target'] === '' && $suggestion['reason'] === '') {
            $output->writeln('  <fg=yellow>Could not get a suggestion. Please try again.</>');
            $output->writeln('');
            return 1;
        }

        // 5. Display
        $this->displaySuggestion($output, $suggestion);

        // 6. Offer to create (only when we have a proper target)
        if ($suggestion['target'] === '') {
            return 0;
        }

        if ($suggestion['type'] === 'spec' || $suggestion['type'] === 'feature') {
            return $this->offerDescribe($input, $output, $suggestion['target']);
        }

        if ($suggestion['type'] === 'example') {
            return $this->offerExemplify($input, $output, $suggestion['target']);
        }

        return 0;
    }

    /**
     * @param array{provider: string, model?: string, api_key: string} $aiConfig
     * @return array{type: string, target: string, reason: string}
     */
    private function getSuggestion(array $aiConfig): array
    {
        if ($this->suggestFn !== null) {
            return ($this->suggestFn)($aiConfig);
        }

        try {
            $provider = ProviderFactory::create($aiConfig);
        } catch (RuntimeException $e) {
            return ['type' => 'info', 'target' => '', 'reason' => $e->getMessage()];
        }

        $model = $aiConfig['model'] ?? ProviderFactory::defaultModel($aiConfig['provider']);

        $projectContext = $this->getCachedProjectContext();
        $systemPrompt = $this->buildSystemPrompt();

        $messages = [
            Message::system($systemPrompt),
            Message::user($projectContext),
        ];

        $response = $provider->chat($messages, [
            'model' => $model,
            'maxTokens' => 8192,
            'temperature' => 0.3,
        ]);

        return $this->parseResponse($response->text);
    }

    /**
     * Returns the project context, using a time-based cache to avoid
     * rescanning the filesystem on every call.
     */
    private function getCachedProjectContext(): string
    {
        $cacheFile = getcwd() . '/.phpspec/next-context.cache';

        if (file_exists($cacheFile)) {
            $contents = file_get_contents($cacheFile);
            $data = $contents !== false ? json_decode($contents, true) : null;
            if (is_array($data) && isset($data['time'], $data['context'])) {
                if (time() - $data['time'] < self::CACHE_TTL) {
                    return $data['context'];
                }
            }
        }

        $context = $this->buildProjectContext();

        $dir = dirname($cacheFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($cacheFile, json_encode([
            'time' => time(),
            'context' => $context,
        ]));

        return $context;
    }

    /**
     * Parses the LLM text response into a structured suggestion.
     *
     * @return array{type: string, target: string, reason: string}
     */
    private function parseResponse(string $text): array
    {
        $json = $text;

        // Strip markdown code fences if present
        if (preg_match('/```(?:json)?\s*(\{.*?\})\s*```/s', $text, $m)) {
            $json = $m[1];
        }

        $data = json_decode($json, true);

        if (is_array($data) && isset($data['type'], $data['target'], $data['reason'])) {
            return [
                'type' => $data['type'],
                'target' => $data['target'],
                'reason' => $data['reason'],
            ];
        }

        // Try to salvage truncated JSON by extracting individual fields
        $type = $this->extractJsonField($text, 'type') ?? 'info';
        $target = $this->extractJsonField($text, 'target') ?? '';
        $reason = $this->extractJsonField($text, 'reason') ?? '';

        return compact('type', 'target', 'reason');
    }

    private function extractJsonField(string $text, string $field): ?string
    {
        if (preg_match('/"' . preg_quote($field, '/') . '"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/', $text, $m)) {
            return stripcslashes($m[1]);
        }
        return null;
    }

    /**
     * @param array{type: string, target: string, reason: string} $suggestion
     */
    private function displaySuggestion(Output $output, array $suggestion): void
    {
        $typeLabel = match ($suggestion['type']) {
            'spec' => 'Describe a spec for',
            'feature' => 'Write a feature scenario for',
            'example' => 'Add an example to',
            default => '',
        };

        if ($typeLabel !== '' && $suggestion['target'] !== '') {
            $output->writeln("  <options=bold>$typeLabel</> <fg=bright-blue;options=bold>{$suggestion['target']}</>");
        }

        if ($suggestion['reason'] !== '') {
            $output->writeln('');
            foreach (explode("\n", wordwrap($suggestion['reason'], 76)) as $line) {
                $output->writeln("  <fg=gray>$line</>");
            }
        }

        $output->writeln('');
    }

    private function offerDescribe(Input $input, Output $output, string $target): int
    {
        $classArg = str_replace('\\', '/', $target);

        if (!$input->isInteractive()) {
            $output->writeln("  <fg=gray>Run:</> bin/phpspec describe $classArg");
            return 0;
        }

        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion(
            '  <fg=yellow>Would you like me to create that for you?</> [Y/n] ',
            true,
        );

        if (!$helper->ask($input, $output, $question)) {
            return 0;
        }

        $application = $this->getApplication();
        if ($application === null) {
            return 1;
        }

        return $application->find('describe')->run(
            new ArrayInput(['class' => $classArg]),
            $output,
        );
    }

    private function offerExemplify(Input $input, Output $output, string $target): int
    {
        $classArg = str_replace('\\', '/', $target);

        $output->writeln("  <fg=gray>Run:</> bin/phpspec exemplify $classArg <method>");

        return 0;
    }

    private function buildSystemPrompt(): string
    {
        return <<<'PROMPT'
You are a BDD coach advising what to build next in a PHP project. Study the project structure and suggest ONE concrete next step — a new class, a new feature scenario, or a new example for an existing spec.

Think about what functionality is MISSING. Look at the domain: what logical next class, behaviour, or capability would extend this project? What would a developer naturally build next? Always suggest something specific and actionable.

NEVER say the project is "well-covered" or "looks good". There is always something to build next.

Respond with ONLY this JSON. Keep "reason" to one short sentence:

{"type": "spec", "target": "App\\Calculator", "reason": "No Calculator exists to evaluate parsed expressions."}

type is one of:
- spec: a new class to create. target = fully qualified class name.
- feature: a user-facing behaviour. target = short name.
- example: a new example for an existing spec. target = fully qualified class name.
PROMPT;
    }

    private function buildProjectContext(): string
    {
        $cwd = getcwd();
        $sections = [];

        // Scan src/
        $srcPath = ltrim($this->config->getSrcPath(), './');
        $srcDir = $cwd . '/' . $srcPath;
        $srcTree = $this->scanTree($srcDir, 2);
        if ($srcTree !== '') {
            $sections[] = "## Source files ($srcPath/)\n$srcTree";
        }

        // Scan spec/
        $specPath = ltrim($this->config->getSpecPath(), './');
        $specDir = $cwd . '/' . $specPath;
        $specTree = $this->scanTree($specDir, 2);
        if ($specTree !== '') {
            $sections[] = "## Spec files ($specPath/)\n$specTree";
        }

        // Scan features/
        $featDir = $cwd . '/features';
        if ($this->filesystem->exists($featDir) && $this->filesystem->isDir($featDir)) {
            $featTree = $this->scanTree($featDir, 2);
            if ($featTree !== '') {
                $sections[] = "## Feature files\n$featTree";
            }
        }

        if (empty($sections)) {
            return 'Empty project — no source, spec, or feature files found.';
        }

        return "# Project structure\n\n" . implode("\n\n", $sections);
    }

    /**
     * Recursively scans a directory tree up to a given depth.
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

    private function loadBootstrap(): bool
    {
        $bootstrap = $this->config->getBootstrap() ?? (file_exists('vendor/autoload.php') ? 'vendor/autoload.php' : null);
        if ($bootstrap === null) {
            return true;
        }
        if (!file_exists($bootstrap)) {
            return false;
        }
        require_once $bootstrap;
        return true;
    }
}
