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
use PhpSpec\Ai\Agent\Proposal;
use PhpSpec\Ai\Contracts\ProviderInterface;
use PhpSpec\Ai\Message;
use PhpSpec\Ai\PromptLibrary;
use PhpSpec\Ai\ProviderFactory;
use PhpSpec\Ai\Response;
use PhpSpec\Ai\Tool;
use PhpSpec\Configuration;
use PhpSpec\Filesystem;
use PhpSpec\Source\Imports;

/**
 * @internal
 * The conversation with the model a refactoring runs on: a plan first, then
 * one baby step at a time, each answered through a tool so it arrives as
 * data, and a fix when a step broke more than it meant to. The model is shown
 * every file it has touched as it now stands, before each step.
 */
final class RefactorModel
{
    private const FILE = [
        'type' => 'object',
        'properties' => [
            'path' => ['type' => 'string', 'description' => 'project-relative path of a PHP file under the source or spec path'],
            'content' => ['type' => 'string', 'description' => 'the whole file as it is after the step'],
        ],
        'required' => ['path', 'content'],
    ];

    /** @var list<Message> */
    private array $messages = [];

    /** @var array<string, true> project-relative paths the model is shown before each step */
    private array $shown = [];

    private ?CommandProfile $profile = null;

    public function __construct(
        private readonly ProviderInterface $provider,
        private readonly Configuration $config,
        private readonly Filesystem $filesystem,
        private readonly string $root,
        private readonly PromptLibrary $prompts = new PromptLibrary(),
    ) {}

    /**
     * Asks for a plan for the target, grounded in its source, its spec and
     * the classes it imports from the source path.
     *
     * @param string $journal the recent refactorings, one per line
     */
    public function plan(RefactorTarget $target, string $journal): RefactorPlan|Declined
    {
        $this->messages = [Message::system($this->profile()->body)];
        $this->shown = [];

        foreach ([$target->sourceFile, $target->specFile, ...$this->importedSources($target->sourceFile)] as $file) {
            $this->shown[$this->relative($file)] = true;
        }

        $ask = sprintf('Plan a refactoring of %s%s.', $target->fqcn, $target->method === null ? '' : sprintf(', focused on its %s() method', $target->method))
            . "\n\n" . $this->currentFiles()
            . ($journal === '' ? '' : "\n\n# Recent refactorings\n" . $journal);

        $response = $this->ask($ask, [$this->tool('propose_plan', [
            'technique' => ['type' => 'string', 'description' => 'the named refactoring'],
            'rationale' => ['type' => 'string', 'description' => 'why it improves the design, in prose'],
            'steps' => ['type' => 'array', 'description' => 'the baby steps, in order', 'items' => [
                'type' => 'object',
                'properties' => [
                    'title' => ['type' => 'string', 'description' => 'the step as an imperative line'],
                    'doing' => ['type' => 'string', 'description' => 'the step under way, a few words: "Extracting DiscountPolicy"'],
                ],
                'required' => ['title', 'doing'],
            ]],
        ]), $this->tool('decline_refactoring', [
            'reason' => ['type' => 'string', 'description' => 'why nothing is worth changing'],
        ])], 'required');

        foreach ($response->toolCalls as $call) {
            if ($call->name === 'propose_plan' && is_array($call->arguments['steps'] ?? null) && $call->arguments['steps'] !== []) {
                return new RefactorPlan(
                    (string) ($call->arguments['technique'] ?? ''),
                    (string) ($call->arguments['rationale'] ?? ''),
                    array_values(array_map($this->plannedStep(...), $call->arguments['steps'])),
                    $response->outputTokens,
                );
            }

            if ($call->name === 'decline_refactoring') {
                return new Declined((string) ($call->arguments['reason'] ?? ''), $response->outputTokens);
            }
        }

        return new Declined(trim($response->text) !== '' ? (string) preg_replace('/\s+/', ' ', trim($response->text)) : 'The model proposed no plan.', $response->outputTokens);
    }

    /**
     * Asks for one step of the plan, showing the model every file it has
     * touched as it now stands.
     *
     * @throws RefusedStepException
     */
    public function step(RefactorPlan $plan, int $index): RefactorStep
    {
        $title = $plan->steps[$index]->title;
        $ask = sprintf('Step %d of %d: %s', $index + 1, count($plan->steps), $title) . "\n\n" . $this->currentFiles();

        return $this->stepFrom($title, $this->ask($ask, [$this->stepTool()], ['name' => 'propose_step']));
    }

    /**
     * Asks for the step again, told what the specs said after it.
     *
     * @throws RefusedStepException
     */
    public function fix(RefactorStep $step, string $failures): RefactorStep
    {
        $ask = "The specs after this step say:\n\n" . $failures . "\n\nPropose the step again, fixed.\n\n" . $this->currentFiles();

        return $this->stepFrom($step->title, $this->ask($ask, [$this->stepTool()], ['name' => 'propose_step']));
    }

    /**
     * @param list<Tool> $tools
     * @param string|array{name: string} $choice
     */
    private function ask(string $content, array $tools, string|array $choice): Response
    {
        $this->messages[] = Message::user($content);
        $response = $this->provider->chat($this->messages, $this->options($tools, $choice));
        $this->messages[] = Message::assistant($response->text, $response->toolCalls ?: null);

        foreach ($response->toolCalls as $call) {
            $this->messages[] = Message::toolResult($call->id, 'Shown to the developer.');
        }

        return $response;
    }

    /**
     * @throws RefusedStepException
     */
    private function stepFrom(string $title, Response $response): RefactorStep
    {
        foreach ($response->toolCalls as $call) {
            if ($call->name !== 'propose_step') {
                continue;
            }

            $files = [];
            foreach (is_array($call->arguments['files'] ?? null) ? $call->arguments['files'] : [] as $file) {
                $files[] = $this->proposal(is_array($file) ? (string) ($file['path'] ?? '') : '', is_array($file) ? (string) ($file['content'] ?? '') : '');
            }

            if ($files !== []) {
                return new RefactorStep($title, $files, (bool) ($call->arguments['red'] ?? false), $response->outputTokens);
            }
        }

        throw new RefusedStepException(sprintf('The model wrote nothing for "%s".', $title));
    }

    /**
     * A step as the plan lists it: an object with its title and how it reads
     * under way, or a bare title that reads the same both ways.
     */
    private function plannedStep(mixed $step): PlannedStep
    {
        if (!is_array($step)) {
            return new PlannedStep((string) $step, (string) $step);
        }

        $title = (string) ($step['title'] ?? '');

        return new PlannedStep($title, (string) ($step['doing'] ?? $title));
    }

    /**
     * @throws RefusedStepException
     */
    private function proposal(string $path, string $content): Proposal
    {
        $path = (string) preg_replace('~^(\./)+~', '', str_replace('\\', '/', $path));
        $roots = [$this->rootOf($this->config->getSrcPath()), $this->rootOf($this->config->getSpecPath())];
        $under = array_filter($roots, static fn(string $root): bool => str_starts_with($path, $root . '/'));

        if ($under === [] || !str_ends_with($path, '.php') || in_array('..', explode('/', $path), true)) {
            throw new RefusedStepException(sprintf('The step writes %s, which is not a PHP file under %s or %s: nothing of it was written.', $path === '' ? 'a file with no path' : $path, ...$roots));
        }

        $file = $this->root . '/' . $path;
        $exists = $this->filesystem->exists($file);
        $this->shown[$path] = true;

        return new Proposal($path, $exists ? $this->filesystem->read($file) : '', $content, !$exists, 'propose_step');
    }

    /**
     * Every file the model has been shown or has written, as it now stands.
     */
    private function currentFiles(): string
    {
        $files = [];
        foreach (array_keys($this->shown) as $path) {
            $file = $this->root . '/' . $path;
            if ($this->filesystem->exists($file)) {
                $files[] = "# $path\n" . $this->filesystem->read($file);
            }
        }

        return implode("\n\n", $files);
    }

    /**
     * The source files under the source path that the target imports.
     *
     * @return list<string>
     */
    private function importedSources(string $sourceFile): array
    {
        $layout = $this->config->getSourceLayout();
        $files = [];

        foreach ((new Imports($this->filesystem->read($sourceFile)))->classes() as $class) {
            $file = $this->root . '/' . str_replace('\\', '/', $layout->relativeFileFor($class));
            if ($this->filesystem->exists($file)) {
                $files[] = $file;
            }
        }

        return $files;
    }

    /**
     * @param list<Tool> $tools
     * @param string|array{name: string} $choice
     * @return array<string, mixed>
     */
    private function options(array $tools, string|array $choice): array
    {
        $ai = $this->config->getAiConfig() ?? ['provider' => 'google'];
        $profile = $this->profile();
        $options = [
            'model' => $ai['model'] ?? ProviderFactory::defaultModel($ai['provider']),
            'maxTokens' => $profile->maxTokens ?? 8192,
            'tools' => $tools,
            'toolChoice' => $choice,
        ];

        if ($profile->temperature !== null) {
            $options['temperature'] = $profile->temperature;
        }

        if (isset($ai['effort'])) {
            $options['effort'] = $ai['effort'];
        }

        return $options;
    }

    private function stepTool(): Tool
    {
        return $this->tool('propose_step', [
            'files' => ['type' => 'array', 'description' => 'every file the step changes or creates, each in full', 'items' => self::FILE],
            'red' => ['type' => 'boolean', 'description' => 'true when the step writes a spec meant to fail until a later step'],
        ]);
    }

    /**
     * @param array<string, array<string, mixed>> $parameters
     */
    private function tool(string $name, array $parameters): Tool
    {
        return Tool::make($name, trim($this->prompts->read('tools/' . $name)), $parameters, static fn(): null => null);
    }

    private function profile(): CommandProfile
    {
        return $this->profile ??= CommandProfile::compose('refactor', ...$this->prompts->stack('commands/refactor'));
    }

    private function relative(string $file): string
    {
        return ltrim(substr($file, strlen($this->root)), '/\\');
    }

    private function rootOf(string $path): string
    {
        return rtrim(ltrim($path, './'), '/');
    }
}
