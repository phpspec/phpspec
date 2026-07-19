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

namespace PhpSpec\Console\Command\Generate;

use PhpSpec\Ai\Message;
use PhpSpec\Ai\ProviderFactory;
use PhpSpec\Configuration;
use PhpSpec\Filesystem;
use RuntimeException;

/**
 * @internal
 * Turns a natural-language instruction into a single proposed file edit — a
 * spec example or a piece of implementation code — authored by the AI. It only
 * *proposes*: the caller shows the diff, confirms, and writes. A spec edit that
 * would drop an existing example is rejected, so growing a spec never loses one.
 */
final class GenerateAgent
{
    /** @var (callable(array{provider: string, model?: string, api_key: string}, string): (string|null))|null the raw AI reply for a built context; injectable for specs */
    private $chatFn;

    /**
     * @param Configuration $config the project configuration
     * @param Filesystem $filesystem the filesystem abstraction
     * @param (callable(array{provider: string, model?: string, api_key: string}, string): (string|null))|null $chatFn injectable AI-chat seam for specs
     */
    public function __construct(
        private readonly Configuration $config,
        private readonly Filesystem $filesystem,
        ?callable $chatFn = null,
    ) {
        $this->chatFn = $chatFn;
    }

    /**
     * Proposes a single file edit for the instruction, or null when the AI
     * produced nothing usable (or the edit would drop a spec example).
     *
     * @param array{provider: string, model?: string, api_key: string} $aiConfig
     * @return array{path: string, old: string, new: string, isNew: bool}|null
     */
    public function propose(array $aiConfig, string $instruction): ?array
    {
        $context = $this->buildContext($instruction);
        $reply = $this->chatFn !== null
            ? ($this->chatFn)($aiConfig, $context)
            : $this->chat($aiConfig, $context);

        $raw = is_string($reply) ? $this->parse($reply) : null;
        if ($raw === null || $raw['path'] === '' || $raw['content'] === '') {
            return null;
        }

        $relPath = ltrim(str_replace('\\', '/', $raw['path']), '/');
        $fullPath = $this->fullPath($relPath);
        $exists = $this->filesystem->exists($fullPath);
        $old = $exists ? $this->filesystem->read($fullPath) : '';
        $new = $raw['content'];

        // A spec edit must never drop an example — specs are grown, not shrunk.
        if ($exists
            && str_ends_with($relPath, $this->config->getSpecSuffix())
            && $this->countExamples($new) < $this->countExamples($old)
        ) {
            return null;
        }

        return ['path' => $relPath, 'old' => $old, 'new' => $new, 'isNew' => !$exists];
    }

    /**
     * Writes an accepted proposal to disk, creating the directory when new.
     *
     * @param array{path: string, old: string, new: string, isNew: bool} $proposal
     */
    public function write(array $proposal): void
    {
        $fullPath = $this->fullPath($proposal['path']);
        $dir = dirname($fullPath);
        if ($proposal['isNew'] && !$this->filesystem->exists($dir)) {
            $this->filesystem->mkdir($dir);
        }

        $this->filesystem->write($fullPath, $proposal['new']);
    }

    /**
     * The absolute path for a project-relative path.
     */
    private function fullPath(string $relPath): string
    {
        return getcwd() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relPath);
    }

    /**
     * Counts the it()/its() examples in spec source, so a rewrite can be
     * checked against dropping one.
     */
    private function countExamples(string $content): int
    {
        return (int) preg_match_all('/\b(?:it|its)\s*\(/', $content);
    }

    /**
     * Sends the built context to the AI and returns its raw reply, or null when
     * no provider can be built.
     *
     * @param array{provider: string, model?: string, api_key: string} $aiConfig
     */
    private function chat(array $aiConfig, string $context): ?string
    {
        try {
            $provider = ProviderFactory::create($aiConfig);
        } catch (RuntimeException) {
            return null;
        }

        $model = $aiConfig['model'] ?? ProviderFactory::defaultModel($aiConfig['provider']);

        return $provider->chat(
            [
                Message::system($this->systemPrompt()),
                Message::user($context),
            ],
            ['model' => $model, 'maxTokens' => 8192, 'temperature' => 0.2],
        )->text;
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
        You turn one natural-language instruction into ONE file edit for a phpspec 9 project.

        Return ONLY this JSON, nothing else:
        {"path": "spec/App/Calculator.spec.php", "content": "<the complete new file content>"}

        - path is project-relative. A spec goes under the spec dir with the .spec.php suffix;
          implementation code goes under the src dir.
        - content is the WHOLE file after your change, not a diff.
        - Specs use the phpspec 9 functional DSL (describe/context/it/let/expect) — never the
          old ObjectBehavior class syntax. Grow a spec: keep every existing example, add or
          refine only what the instruction asks for.
        - Implementation is plain PHP with the smallest change that satisfies the instruction.
        - Make the smallest change that fulfils the instruction. Do not invent unrelated code.
        PROMPT;
    }

    /**
     * Builds the user message: the instruction, the project file tree, and the
     * current contents of any spec/source files whose class the instruction names.
     */
    private function buildContext(string $instruction): string
    {
        $cwd = getcwd();
        $specPath = ltrim($this->config->getSpecPath(), './');
        $srcPath = ltrim($this->config->getSrcPath(), './');

        $sections = ["# Instruction\n$instruction"];

        $tree = $this->scanTree($cwd . '/' . $srcPath) . "\n" . $this->scanTree($cwd . '/' . $specPath);
        if (trim($tree) !== '') {
            $sections[] = "# Project files\n$tree";
        }

        foreach ($this->relevantFiles($instruction, $specPath, $srcPath) as $rel => $content) {
            $sections[] = "# $rel\n$content";
        }

        return implode("\n\n", $sections);
    }

    /**
     * The existing spec/source files for any class-like token in the instruction.
     *
     * @return array<string, string> relative path => contents
     */
    private function relevantFiles(string $instruction, string $specPath, string $srcPath): array
    {
        $files = [];
        preg_match_all('/\b[A-Z][A-Za-z0-9]+\b/', $instruction, $matches);

        foreach (array_unique($matches[0]) as $class) {
            foreach (["$srcPath/$class.php", "$specPath/$class" . $this->config->getSpecSuffix()] as $rel) {
                $full = getcwd() . '/' . $rel;
                if ($this->filesystem->exists($full)) {
                    $files[$rel] = $this->filesystem->read($full);
                }
            }
        }

        return $files;
    }

    /**
     * A shallow listing of the files under a directory, one per line.
     */
    private function scanTree(string $dir): string
    {
        if (!$this->filesystem->exists($dir) || !$this->filesystem->isDir($dir)) {
            return '';
        }

        $lines = [];
        foreach ($this->filesystem->scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $full = $dir . '/' . $entry;
            $lines[] = $this->filesystem->isDir($full) ? "$entry/" : $entry;
        }

        return implode("\n", $lines);
    }

    /**
     * Parses the AI reply into a {path, content} pair, or null when unusable.
     *
     * @return array{path: string, content: string}|null
     */
    private function parse(string $text): ?array
    {
        $json = $text;
        if (preg_match('/```(?:json)?\s*(\{.*\})\s*```/s', $text, $m)) {
            $json = $m[1];
        }

        $data = json_decode($json, true);
        if (is_array($data) && is_string($data['path'] ?? null) && is_string($data['content'] ?? null)) {
            return ['path' => $data['path'], 'content' => $data['content']];
        }

        return null;
    }
}
