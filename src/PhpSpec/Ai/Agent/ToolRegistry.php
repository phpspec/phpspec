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

namespace PhpSpec\Ai\Agent;

use PhpSpec\Ai\Contracts\ToolInterface;
use PhpSpec\Ai\PromptLibrary;
use PhpSpec\Ai\Tool;
use PhpSpec\Ai\ToolCall;
use PhpSpec\CodeGeneration\ClassGenerator;
use PhpSpec\CodeGeneration\FeatureGenerator;
use PhpSpec\CodeGeneration\StepGenerator;
use PhpSpec\Configuration;
use PhpSpec\Filesystem;
use RuntimeException;

/**
 * @internal
 * The shared tool definitions of the agent pipeline. Each tool's model-facing
 * description lives in `Ai/Prompts/tools/<name>.txt` (editable text); its
 * schema and deterministic backing generator live here. Tools only ever return
 * Proposals; nothing touches disk. The registry also owns the deterministic
 * short-circuit: when the step fully determines the artifact (a feature
 * skeleton, the steps of a known feature), the model is never consulted.
 */
final class ToolRegistry
{
    private const SCHEMAS = [
        'write_feature' => [
            'name' => ['type' => 'string', 'description' => 'Short feature name (a snake_case slug)', 'default' => ''],
            'path' => ['type' => 'string', 'description' => 'Explicit project-relative .feature path', 'default' => ''],
        ],
        'write_steps' => [
            'feature_path' => ['type' => 'string', 'description' => 'Project-relative path of the .feature to write steps for', 'default' => ''],
        ],
        'propose_edit' => [
            'path' => ['type' => 'string', 'description' => 'Project-relative path of the file'],
            'content' => ['type' => 'string', 'description' => 'The complete new file content, not a diff'],
        ],
    ];

    private readonly PromptLibrary $prompts;

    private readonly FeatureGenerator $featureGenerator;

    private readonly StepGenerator $stepGenerator;

    /**
     * @param Configuration $config the project configuration (layout paths)
     * @param Filesystem $filesystem filesystem abstraction for testability
     * @param PromptLibrary|null $prompts loads the tool descriptions
     */
    public function __construct(
        private readonly Configuration $config,
        private readonly Filesystem $filesystem,
        ?PromptLibrary $prompts = null,
    ) {
        $this->prompts = $prompts ?? new PromptLibrary($filesystem);
        $this->featureGenerator = new FeatureGenerator();
        $this->stepGenerator = new StepGenerator($filesystem);
    }

    /**
     * The tool definitions a command's manifest declares, descriptions read
     * from their prompt files.
     *
     * @return list<ToolInterface>
     */
    public function definitions(CommandProfile $profile): array
    {
        $tools = [];

        foreach ($profile->tools as $name) {
            if (!isset(self::SCHEMAS[$name])) {
                throw new RuntimeException(sprintf('Unknown tool "%s" declared by command "%s".', $name, $profile->name));
            }

            $tools[] = Tool::make(
                name: $name,
                description: trim($this->prompts->read('tools/' . $name)),
                parameters: self::SCHEMAS[$name],
                handler: static fn(array $arguments): string => '',
            );
        }

        return $tools;
    }

    /**
     * The proposals fully determined by the step alone, or null when the model
     * is needed: a feature skeleton at a known path or slug, or the steps file
     * of a known feature. Throws when the determined action is impossible, so
     * the user gets the real reason instead of a generic failure.
     *
     * @return list<Proposal>|null
     */
    public function deterministic(?Step $step, Grounding $grounding): ?array
    {
        if ($step === null) {
            return null;
        }

        if ($step->phase === Phase::WriteFeature) {
            $path = $this->featurePath($step);

            return $path === null ? null : [$this->featureProposal($path)];
        }

        if ($step->phase === Phase::WriteSteps) {
            $feature = $step->subject ?? self::featureBesideSteps($step->path);

            return $feature === null ? null : [$this->stepsProposal($feature)];
        }

        return null;
    }

    /**
     * Executes the model's tool calls into proposals, never touching disk. A
     * path the step derived always wins over the path the model chose, and a
     * spec written in phpspec-8 ObjectBehavior syntax is rejected outright.
     *
     * @param ToolCall[] $toolCalls
     * @return list<Proposal>
     */
    public function fromCalls(array $toolCalls, ?Step $step): array
    {
        $proposals = [];

        foreach ($toolCalls as $call) {
            $proposal = match ($call->name) {
                'write_feature' => $this->featureCall($step, $call->arguments),
                'write_steps' => $this->stepsCall($step, $call->arguments),
                'propose_edit' => $this->editCall($step, $call->arguments),
                default => null,
            };

            if ($proposal !== null) {
                $proposals[] = $proposal;
            }
        }

        return $proposals;
    }

    /**
     * A write_feature call: the step's own path or slug wins over the model's
     * arguments; with neither, nothing is proposed.
     *
     * @param array<string, mixed> $arguments
     */
    private function featureCall(?Step $step, array $arguments): ?Proposal
    {
        $path = ($step !== null ? $this->featurePath($step) : null)
            ?? $this->slugPath((string) ($arguments['name'] ?? ''))
            ?? $this->nonEmpty((string) ($arguments['path'] ?? ''));

        return $path === null ? null : $this->featureProposal($this->relative($path));
    }

    /**
     * A write_steps call: the step's subject feature wins over the argument.
     *
     * @param array<string, mixed> $arguments
     */
    private function stepsCall(?Step $step, array $arguments): ?Proposal
    {
        $feature = $step !== null ? $step->subject : null;
        $feature ??= $this->nonEmpty((string) ($arguments['feature_path'] ?? ''));

        return $feature === null ? null : $this->stepsProposal($feature);
    }

    /**
     * A propose_edit call: the step-derived path wins over the model's, and
     * legacy ObjectBehavior spec content is rejected with the reason.
     *
     * @param array<string, mixed> $arguments
     */
    private function editCall(?Step $step, array $arguments): ?Proposal
    {
        $path = $this->derivedPath($step) ?? $this->relative((string) ($arguments['path'] ?? ''));
        $content = (string) ($arguments['content'] ?? '');
        if ($path === '' || $content === '') {
            return null;
        }

        if (str_ends_with($path, $this->config->getSpecSuffix()) && self::looksLikeLegacySpec($content)) {
            throw new RuntimeException('The proposed spec uses phpspec 8 ObjectBehavior syntax; phpspec 9 specs use the describe/it/expect DSL, so it was rejected.');
        }

        return $this->proposal($path, $content, 'propose_edit');
    }

    /**
     * The project-relative path a step derives through the configured layout:
     * an explicit path verbatim, a spec mirroring the class under the spec dir,
     * or source with the PSR-4 prefix stripped, exactly as phpspec's own
     * generators lay files out.
     */
    private function derivedPath(?Step $step): ?string
    {
        if ($step === null) {
            return null;
        }

        if ($step->path !== null) {
            return $this->relative($step->path);
        }

        if ($step->subject === null) {
            return null;
        }

        if ($step->phase === Phase::WriteSpec) {
            $specDir = ltrim(str_replace('\\', '/', $this->config->getSpecPath()), './');

            return $specDir . '/' . str_replace('\\', '/', $step->subject) . $this->config->getSpecSuffix();
        }

        if ($step->phase === Phase::WriteCode) {
            $srcDir = ltrim(str_replace('\\', '/', $this->config->getSrcPath()), './');

            return $this->relative(ClassGenerator::resolveFqcn($step->subject, $srcDir, $this->config->getPsr4Prefix())['filePath']);
        }

        if ($step->phase === Phase::WriteFeature) {
            return $this->featurePath($step);
        }

        return null;
    }

    /**
     * The feature path a write-feature step determines: its explicit path, or
     * the slug under the configured features path.
     */
    private function featurePath(Step $step): ?string
    {
        if ($step->path !== null) {
            return $this->relative($step->path);
        }

        return $this->slugPath($step->subject ?? '');
    }

    /**
     * The feature path for a slug under the configured features path, or null
     * for an empty slug.
     */
    private function slugPath(string $slug): ?string
    {
        if ($slug === '') {
            return null;
        }

        return rtrim(trim($this->config->getFeaturesPath(), './'), '/') . '/' . $slug . '.feature';
    }

    /**
     * A deterministic Gherkin skeleton proposal at the given path.
     */
    private function featureProposal(string $path): Proposal
    {
        return $this->proposal($path, $this->featureGenerator->skeleton(FeatureGenerator::titleFromPath($path)), 'write_feature');
    }

    /**
     * The steps-file proposal for a feature: the feature is parsed and the
     * missing step definitions drafted beside it, in the same layout the
     * runner's own generator uses (`<feature dir>/steps/<name>.steps.php`).
     */
    private function stepsProposal(string $featurePath): Proposal
    {
        $relFeature = $this->relative($featurePath);
        $absFeature = $this->absolute($relFeature);
        if (!$this->filesystem->exists($absFeature)) {
            throw new RuntimeException(sprintf('Feature file "%s" was not found, so there are no steps to write.', $relFeature));
        }

        $steps = StepGenerator::parseSteps($this->filesystem->read($absFeature));
        if ($steps === []) {
            throw new RuntimeException(sprintf('No Given/When/Then steps found in "%s".', $relFeature));
        }

        $relSteps = dirname($relFeature) . '/steps/' . basename($relFeature, '.feature') . '.steps.php';
        $absSteps = $this->absolute($relSteps);
        $existing = $this->filesystem->exists($absSteps) ? $this->filesystem->read($absSteps) : '';

        return new Proposal($relSteps, $existing, $this->stepGenerator->skeleton($steps, $existing), $existing === '', 'write_steps');
    }

    /**
     * The feature that a named `.steps.php` path belongs to, in the standard
     * layout (`features/steps/x.steps.php` steps `features/x.feature`).
     */
    private static function featureBesideSteps(?string $stepsPath): ?string
    {
        if ($stepsPath === null || !str_ends_with($stepsPath, '.steps.php')) {
            return null;
        }

        return dirname($stepsPath, 2) . '/' . basename($stepsPath, '.steps.php') . '.feature';
    }

    /**
     * Whether spec content uses phpspec-8 ObjectBehavior idioms rather than the
     * phpspec-9 functional DSL: an "ObjectBehavior" class, the old `spec\` file
     * namespace, `->shouldXxx()` matchers, or a method called directly on
     * `$this` (the subject), none of which are valid phpspec 9.
     */
    private static function looksLikeLegacySpec(string $content): bool
    {
        return str_contains($content, 'ObjectBehavior')
            || str_contains($content, 'namespace spec\\')
            || preg_match('~->should[A-Z]~', $content) === 1
            || preg_match('~\$this->\w+\s*\(~', $content) === 1;
    }

    /**
     * A proposal for a path and content, reading any existing file so the
     * presenter can show a modified-file diff.
     */
    private function proposal(string $path, string $content, string $origin): Proposal
    {
        $relPath = $this->relative($path);
        $absPath = $this->absolute($relPath);
        $exists = $this->filesystem->exists($absPath);

        return new Proposal($relPath, $exists ? $this->filesystem->read($absPath) : '', $content, !$exists, $origin);
    }

    /**
     * Normalises a path to project-relative with forward slashes (both sides
     * separator-normalised, so a Windows cwd still strips cleanly).
     */
    private function relative(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $cwd = str_replace('\\', '/', getcwd() ?: '.') . '/';
        if (str_starts_with($path, $cwd)) {
            $path = substr($path, strlen($cwd));
        }

        return ltrim($path, '/');
    }

    /**
     * The absolute path for a project-relative one.
     */
    private function absolute(string $relPath): string
    {
        return (getcwd() ?: '.') . '/' . $relPath;
    }

    /**
     * The string itself, or null when empty; keeps argument fallbacks terse.
     */
    private function nonEmpty(string $value): ?string
    {
        return $value === '' ? null : $value;
    }
}
