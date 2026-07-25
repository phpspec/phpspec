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
use PhpSpec\CodeGeneration\ClassGenerator;
use PhpSpec\CodeGeneration\FeatureGenerator;
use PhpSpec\Configuration;
use PhpSpec\Filesystem;
use RuntimeException;

/**
 * @internal
 * Turns a natural-language instruction into a single proposed file edit — a
 * spec example or a piece of implementation code — authored by the AI. It only
 * *proposes*: the caller shows the diff, confirms, and writes, so any change
 * (including one that removes an example) is visible before it lands.
 */
final class GenerateAgent
{
    /** @var (callable(array{provider: string, model?: string, api_key: string}, string): (string|null))|null the raw AI reply for a built context; injectable for specs */
    private $chatFn;

    private readonly FeatureGenerator $featureGenerator;

    /**
     * @param Configuration $config the project configuration
     * @param Filesystem $filesystem the filesystem abstraction
     * @param (callable(array{provider: string, model?: string, api_key: string}, string): (string|null))|null $chatFn injectable AI-chat seam for specs
     * @param FeatureGenerator|null $featureGenerator deterministic Gherkin generator for feature targets
     */
    public function __construct(
        private readonly Configuration $config,
        private readonly Filesystem $filesystem,
        ?callable $chatFn = null,
        ?FeatureGenerator $featureGenerator = null,
    ) {
        $this->chatFn = $chatFn;
        $this->featureGenerator = $featureGenerator ?? new FeatureGenerator();
    }

    /**
     * Proposes a single file edit for the instruction, or null when the AI
     * produced nothing usable.
     *
     * @param array{provider: string, model?: string, api_key: string} $aiConfig
     * @return array{path: string, old: string, new: string, isNew: bool}|null
     */
    public function propose(array $aiConfig, string $instruction): ?array
    {
        $target = InstructionTarget::parse($instruction);
        $targetPath = $target !== null ? $this->resolveTargetPath($target) : null;

        // A .feature target is fully deterministic: the path and a valid Gherkin
        // skeleton are ours, so a wrong-artifact or phpspec-8 model reply can't
        // leak through — the model is not consulted for the file's shape.
        if ($targetPath !== null && $target['type'] === 'feature') {
            return $this->proposeContent(
                $targetPath,
                $this->featureGenerator->skeleton(FeatureGenerator::titleFromPath($targetPath)),
            );
        }

        $context = $this->buildContext($instruction);
        $reply = $this->chatFn !== null
            ? ($this->chatFn)($aiConfig, $context)
            : $this->chat($aiConfig, $context);

        $raw = is_string($reply) ? $this->parse($reply) : null;
        if ($raw === null || $raw['content'] === '') {
            return null;
        }

        // Honour the path derived from the instruction over the model's choice.
        $path = $targetPath ?? $raw['path'];
        if ($path === '') {
            return null;
        }

        // Never propose a spec written in phpspec-8 ObjectBehavior syntax — reject
        // it rather than write invalid DSL that would fatal when phpspec loads it.
        if (str_ends_with($path, '.spec.php') && self::looksLikeLegacySpec($raw['content'])) {
            return null;
        }

        return $this->proposeContent($path, $raw['content']);
    }

    /**
     * Resolves an instruction target to a project-relative path using the project's
     * configured layout — the src/spec/features directories, the spec suffix, and
     * the PSR-4 prefix — never hardcoded directories. An explicit path the user
     * named is used as-is. Specs mirror the full namespace under the spec dir;
     * source strips the PSR-4 prefix, exactly as phpspec's own generators do.
     *
     * @param array{type: 'feature'|'spec'|'code', path?: string, slug?: string, class?: string} $target
     */
    private function resolveTargetPath(array $target): string
    {
        if (isset($target['path'])) {
            return $target['path'];
        }

        if ($target['type'] === 'feature') {
            return rtrim($this->config->getFeaturesPath(), '/') . '/' . ($target['slug'] ?? '') . '.feature';
        }

        $class = $target['class'] ?? '';

        if ($target['type'] === 'spec') {
            $specDir = ltrim(str_replace('\\', '/', $this->config->getSpecPath()), './');

            return $specDir . '/' . str_replace('\\', '/', $class) . $this->config->getSpecSuffix();
        }

        $srcDir = ltrim(str_replace('\\', '/', $this->config->getSrcPath()), './');
        $absolute = ClassGenerator::resolveFqcn($class, $srcDir, $this->config->getPsr4Prefix())['filePath'];
        $cwd = getcwd() . DIRECTORY_SEPARATOR;
        $relative = str_starts_with($absolute, $cwd) ? substr($absolute, strlen($cwd)) : $absolute;

        return str_replace(DIRECTORY_SEPARATOR, '/', $relative);
    }

    /**
     * Whether spec content uses phpspec-8 ObjectBehavior idioms rather than the
     * phpspec-9 functional DSL: an "ObjectBehavior" class, the old `spec\` file
     * namespace, `->shouldXxx()` matchers, or a method called directly on `$this`
     * (the subject) — none of which are valid phpspec-9 (where `$this->x` is only
     * a `let`-bound value).
     */
    private static function looksLikeLegacySpec(string $content): bool
    {
        return str_contains($content, 'ObjectBehavior')
            || str_contains($content, 'namespace spec\\')
            || preg_match('~->should[A-Z]~', $content) === 1
            || preg_match('~\$this->\w+\s*\(~', $content) === 1;
    }

    /**
     * Builds a proposal for a known project-relative path and content, reading any
     * existing file so the caller can show a modified-file diff.
     *
     * @return array{path: string, old: string, new: string, isNew: bool}
     */
    private function proposeContent(string $path, string $content): array
    {
        $relPath = ltrim(str_replace('\\', '/', $path), '/');
        $fullPath = $this->fullPath($relPath);
        $exists = $this->filesystem->exists($fullPath);

        return [
            'path' => $relPath,
            'old' => $exists ? $this->filesystem->read($fullPath) : '',
            'new' => $content,
            'isNew' => !$exists,
        ];
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
