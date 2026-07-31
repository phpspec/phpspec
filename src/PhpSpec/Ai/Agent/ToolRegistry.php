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

use Closure;
use PhpSpec\Ai\Contracts\ToolInterface;
use PhpSpec\Ai\PromptLibrary;
use PhpSpec\Ai\Tool;
use PhpSpec\Ai\ToolCall;
use PhpSpec\CodeGeneration\ClassGenerator;
use PhpSpec\CodeGeneration\FeatureGenerator;
use PhpSpec\CodeGeneration\FeatureLayout;
use PhpSpec\CodeGeneration\LegacySpecDetector;
use PhpSpec\CodeGeneration\StepGenerator;
use PhpSpec\Configuration;
use PhpSpec\Filesystem;
use PhpSpec\StoryBDD\StepVocabulary;
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
    /**
     * The shared `intent` parameter of every pair write tool: the driver's
     * one-line plan, surfaced to the navigator at the confirm prompt so the
     * go-ahead is a real decision, not a blind "allow this action?".
     */
    private const INTENT = [
        'type' => 'string',
        'description' => 'One line stating what this change does and why, shown to the '
            . 'navigator at the confirm prompt (e.g. "add an example that total() sums the line items").',
    ];

    private const SCHEMAS = [
        'write_feature' => [
            'name' => ['type' => 'string', 'description' => 'Short feature name (a snake_case slug)', 'default' => ''],
            'path' => ['type' => 'string', 'description' => 'Explicit project-relative .feature path', 'default' => ''],
            'content' => ['type' => 'string', 'description' => 'The complete Gherkin content, starting with "Feature:" and holding at least one concrete Scenario', 'default' => ''],
        ],
        'write_steps' => [
            'feature_path' => ['type' => 'string', 'description' => 'Project-relative path of the .feature to write steps for', 'default' => ''],
        ],
        'propose_edit' => [
            'path' => ['type' => 'string', 'description' => 'Project-relative path of the file'],
            'content' => ['type' => 'string', 'description' => 'The complete new file content, not a diff'],
        ],
        'suggest_next' => [
            'type' => ['type' => 'string', 'enum' => ['spec', 'feature', 'example', 'steps', 'implement', 'refactor', 'info'], 'description' => 'What to do next: spec (a class to describe), feature (a user-facing behaviour), example (grow an existing spec with a NEW example), steps (a feature\'s step definitions need writing, or their bodies are pending or failing; target is the .feature path), implement (source code must change to make the suite green; target is the class), refactor (the suite is green and a class deserves cleaning), or info'],
            'target' => ['type' => 'string', 'description' => 'The class name, feature path, or spec the suggestion is about'],
            'reason' => ['type' => 'string', 'description' => 'One short sentence on why this is the next baby step'],
        ],
        'describe' => [
            'class_name' => ['type' => 'string', 'description' => 'Class path using forward slashes (e.g. "App/Calculator")'],
            'intent' => self::INTENT,
        ],
        'add_example' => [
            'class_name' => ['type' => 'string', 'description' => 'Class path using forward slashes (e.g. "App/Calculator")'],
            'method' => ['type' => 'string', 'description' => 'The method name to add an example for (e.g. "add")'],
            'intent' => self::INTENT,
        ],
        'generate_feature' => [
            'feature_name' => ['type' => 'string', 'description' => 'Feature file name without extension (e.g. "user-registration")'],
            'content' => ['type' => 'string', 'description' => 'The complete Gherkin feature file content'],
            'intent' => self::INTENT,
        ],
        'generate_steps' => [
            'feature_name' => ['type' => 'string', 'description' => 'Step file name without extension (e.g. "user-registration")'],
            'content' => ['type' => 'string', 'description' => 'The complete PHP step definitions file content'],
            'intent' => self::INTENT,
        ],
        'write_file' => [
            'path' => ['type' => 'string', 'description' => 'Relative path from project root (e.g. "src/App/Service.php")'],
            'content' => ['type' => 'string', 'description' => 'The complete file content'],
            'intent' => self::INTENT,
        ],
        'update_file' => [
            'path' => ['type' => 'string', 'description' => 'Relative path from project root (e.g. "src/App/Service.php")'],
            'content' => ['type' => 'string', 'description' => 'The complete new file content'],
            'intent' => self::INTENT,
        ],
        'offer_change' => [
            'path' => ['type' => 'string', 'description' => 'Relative path from project root (e.g. "src/App/Service.php")'],
            'content' => ['type' => 'string', 'description' => 'The complete new file content'],
            'intent' => self::INTENT,
        ],
        'ask_user' => [
            'question' => ['type' => 'string', 'description' => 'The question to show the user, e.g. "Do you want me to generate the method fooBar()?"'],
            'action' => ['type' => 'string', 'description' => 'Short verb phrase naming the action, completing the sentence "always ..." (e.g. "generate methods")'],
        ],
        'run_specs' => [
            'path' => ['type' => 'string', 'description' => 'Optional path to run (e.g. "spec/App/Calculator.spec.php"). Leave empty to run all.', 'default' => ''],
        ],
        'inspect_symbol' => [
            'fqcn' => ['type' => 'string', 'description' => 'Fully-qualified class name, e.g. "App\\Calculator"'],
        ],
        'read_file' => [
            'path' => ['type' => 'string', 'description' => 'Absolute or relative path to a file'],
        ],
        'list_files' => [
            'directory' => ['type' => 'string', 'description' => 'Relative directory path (e.g. "spec/App", "src/App")', 'default' => '.'],
        ],
    ];

    private readonly PromptLibrary $prompts;

    private readonly FeatureGenerator $featureGenerator;

    private readonly StepGenerator $stepGenerator;

    private readonly FeatureLayout $layout;

    private readonly StepVocabulary $vocabulary;

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
        $this->layout = new FeatureLayout();
        $this->vocabulary = new StepVocabulary($filesystem);
    }

    /**
     * The tool definitions a command's manifest declares, descriptions read
     * from their prompt files. Without handlers every tool is propose-only (a
     * no-op the pipeline turns into Proposals); a caller running a live session
     * binds its own handler per name, and execution stays the caller's concern.
     *
     * @param array<string, Closure> $handlers live handlers keyed by tool name
     * @return list<ToolInterface>
     */
    public function definitions(CommandProfile $profile, array $handlers = []): array
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
                handler: $handlers[$name] ?? static fn(array $arguments): string => '',
            );
        }

        return $tools;
    }

    /**
     * The proposals fully determined by the step alone, or null when the model
     * is needed: a feature skeleton at a known path or slug, or the steps file
     * of a known feature. Only tools the command's manifest declares may
     * short-circuit (an advising command derives steps too, but never writes).
     * Throws when the determined action is impossible, so the user gets the
     * real reason instead of a generic failure.
     *
     * @return list<Proposal>|null
     */
    public function deterministic(?Step $step, Grounding $grounding, CommandProfile $profile): ?array
    {
        if ($step === null) {
            return null;
        }

        if ($step->phase === Phase::WriteSteps && in_array('write_steps', $profile->tools, true)) {
            $feature = $step->subject ?? $this->featureBesideSteps($step->path);
            if ($feature === null) {
                return null;
            }

            $proposal = $this->stepsProposal($feature);
            if ($this->scaffoldsNothing($proposal)) {
                // Every step is already defined: nothing is determined here.
                // The human wants content (the bodies), which is the model's job.
                return null;
            }

            return [$proposal];
        }

        return null;
    }

    /**
     * The structured report a reporting tool call carries (suggest_next's
     * {type, target, reason}), or an empty array when none was called. Reports
     * are terminal answers, not proposals; nothing reaches disk.
     *
     * @param ToolCall[] $toolCalls
     * @return array<string, string>
     */
    public function reportFrom(array $toolCalls): array
    {
        foreach ($toolCalls as $call) {
            if ($call->name === 'suggest_next') {
                return array_map(strval(...), array_filter($call->arguments, is_scalar(...)));
            }
        }

        return [];
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
                // The step owns the artifact type: a write-feature step turns
                // any edit answer into feature content (Gherkin or fallback),
                // so a model that reaches for the wrong tool cannot write a
                // spec where a feature belongs.
                'propose_edit' => $step?->phase === Phase::WriteFeature
                    ? $this->featureCall($step, $call->arguments)
                    : $this->editCall($step, $call->arguments),
                default => null,
            };

            if ($proposal !== null) {
                $proposals[] = $proposal;
            }
        }

        return $proposals;
    }

    /**
     * The skeleton stand-in for a write-feature step whose ask produced no
     * usable proposal, so the loop still moves with a valid artifact at the
     * derived path. Null when the step is no write-feature, no path derives,
     * or the file exists (an existing feature is never scaffolded over).
     */
    public function featureFallback(?Step $step): ?Proposal
    {
        if ($step === null || $step->phase !== Phase::WriteFeature) {
            return null;
        }

        $path = $this->featurePath($step);
        if ($path === null || $this->filesystem->exists($this->absolute($path))) {
            return null;
        }

        return $this->featureProposal($path);
    }

    /**
     * A write_feature call: the step's own path or slug wins over the model's
     * arguments (with neither, nothing is proposed), and the model's Gherkin
     * is the content. The skeleton stands in only when no Gherkin arrived: a
     * feature's scenarios are imagination, never scaffolding.
     *
     * @param array<string, mixed> $arguments
     */
    private function featureCall(?Step $step, array $arguments): ?Proposal
    {
        $path = ($step !== null ? $this->featurePath($step) : null)
            ?? $this->slugPath((string) ($arguments['name'] ?? ''))
            ?? $this->nonEmpty((string) ($arguments['path'] ?? ''));

        if ($path === null || $this->filesystem->exists($this->absolute($this->relative($path)))) {
            return null;
        }

        $content = trim((string) ($arguments['content'] ?? ''));
        if (!str_contains($content, 'Feature:')) {
            return $this->featureProposal($this->relative($path));
        }

        return $this->proposal($this->relative($path), $content . "\n", 'write_feature');
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
        if ($feature === null) {
            return null;
        }

        $proposal = $this->stepsProposal($feature);

        // A scaffold that adds nothing is not an answer; filling in existing
        // step bodies is propose_edit's job.
        return $this->scaffoldsNothing($proposal) ? null : $proposal;
    }

    /**
     * Whether a scaffold proposal adds nothing meaningful: the generator may
     * normalise the trailing newline, so a whitespace-only difference still
     * counts as nothing to add.
     */
    private function scaffoldsNothing(Proposal $proposal): bool
    {
        return rtrim($proposal->new) === rtrim($proposal->old);
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

        if (str_ends_with($path, $this->config->getSpecSuffix()) && LegacySpecDetector::looksLegacy($content)) {
            throw new RuntimeException('The proposed spec uses phpspec 8 ObjectBehavior syntax; phpspec 9 specs use the describe/it/expect DSL, so it was rejected.');
        }

        if (str_ends_with($path, '.steps.php')) {
            $rejection = $this->vocabulary->rejectionFor($content, $path, $this->featuresRoot());
            if ($rejection !== null) {
                throw new RuntimeException($rejection);
            }
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
            return $this->locatedPath($this->relative($step->path));
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
            return $this->locatedPath($this->relative($step->path));
        }

        return $this->slugPath($step->subject ?? '');
    }

    /**
     * A bare file name is a name, not a location: the runner only discovers
     * files under the configured directories, so the name is placed in the
     * directory its suffix belongs to (feature, spec, or source). A path that
     * already names a directory is honoured verbatim, and steps files are
     * laid out beside their feature elsewhere.
     */
    private function locatedPath(string $path): string
    {
        if (str_starts_with($path, './')) {
            $path = substr($path, 2);
        }

        if (str_contains($path, '/') || str_ends_with($path, '.steps.php')) {
            return $path;
        }

        $dir = match (true) {
            str_ends_with($path, '.feature') => trim($this->config->getFeaturesPath(), './'),
            str_ends_with($path, $this->config->getSpecSuffix()) => ltrim(str_replace('\\', '/', $this->config->getSpecPath()), './'),
            str_ends_with($path, '.php') => ltrim($this->config->getSrcPath(), './'),
            default => null,
        };

        if ($dir === null) {
            return $path;
        }

        return rtrim($dir, '/') . '/' . $path;
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

        $relSteps = $this->layout->stepsPathFor($relFeature);
        $absSteps = $this->absolute($relSteps);
        $existing = $this->filesystem->exists($absSteps) ? $this->filesystem->read($absSteps) : '';

        // A title another steps file owns is already defined for the whole
        // suite, so the scaffold skips it instead of planting a duplicate
        // that would error at the next load.
        $foreign = [];
        foreach ($this->vocabulary->definedTitles($this->featuresRoot()) as $title => $file) {
            if (basename($file) !== basename($relSteps)) {
                $foreign[] = $title;
            }
        }

        return new Proposal($relSteps, $existing, $this->stepGenerator->skeleton($steps, $existing, $foreign), $existing === '', 'write_steps');
    }

    /**
     * The absolute features root the vocabulary scans.
     */
    private function featuresRoot(): string
    {
        return $this->absolute(trim($this->config->getFeaturesPath(), './'));
    }

    /**
     * The feature that a named `.steps.php` path belongs to, in the standard
     * layout (`features/steps/x.steps.php` steps `features/x.feature`).
     */
    private function featureBesideSteps(?string $stepsPath): ?string
    {
        if ($stepsPath === null || !str_ends_with($stepsPath, '.steps.php')) {
            return null;
        }

        return $this->layout->featurePathFor($stepsPath);
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
     * Normalises a path to project-relative with forward slashes.
     */
    private function relative(string $path): string
    {
        return ProjectPath::relative($path);
    }

    /**
     * The absolute path for a project-relative one.
     */
    private function absolute(string $relPath): string
    {
        return ProjectPath::absolute($relPath);
    }

    /**
     * The string itself, or null when empty; keeps argument fallbacks terse.
     */
    private function nonEmpty(string $value): ?string
    {
        return $value === '' ? null : $value;
    }
}
