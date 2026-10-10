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

namespace PhpSpec\StoryBDD;

use PhpSpec\Attachments;
use PhpSpec\CapturedNotes;
use PhpSpec\CapturedOutput;
use PhpSpec\EventDispatcher\DispatcherRegistry;
use PhpSpec\OwnCode;
use PhpSpec\Result\FeatureResult;
use PhpSpec\Result\ScenarioResult;
use PhpSpec\Result\StepResult;
use PhpSpec\Results;
use PhpSpec\Specification\PendingException;
use PhpSpec\Specification\SkippedException;
use PhpSpec\Specification\SpecBlock;
use PhpSpec\StopRegistry;
use PhpSpec\TitleFilter;

/**
 * @internal
 * Runs a parsed .feature file by executing scenarios and their steps against the step registry.
 * Handles Background steps, Scenario Outlines, hook invocation, and step match collection.
 */
final readonly class Feature implements SpecBlock
{
    /**
     * Creates a Feature runner for a parsed .feature file.
     *
     * @param string $path filesystem path to the .feature file
     * @param FeatureNode $featureNode parsed feature structure
     * @param StepRegistry $registry step pattern-to-closure mappings
     * @param HookRegistry $hooks before-feature/scenario/step hooks
     */
    public function __construct(
        private string $path,
        private FeatureNode $featureNode,
        private StepRegistry $registry,
        private HookRegistry $hooks,
    ) {}

    /**
     * A copy of this feature reduced to the scenarios the tag expression
     * selects, each scenario carrying the feature's tags with its own, or
     * null when it selects none.
     */
    public function withScenariosTagged(TagExpression $tags): ?self
    {
        $scenarios = array_values(array_filter(
            $this->featureNode->scenarios,
            fn(ScenarioNode $scenario) => $tags->matches([...$this->featureNode->tags, ...$scenario->tags]),
        ));

        return $scenarios === [] ? null : $this->withScenarios($scenarios);
    }

    /**
     * Returns a copy of this feature reduced to the scenarios whose title
     * matches the filter, or null when no scenario matches.
     *
     * @param TitleFilter $filter the active title filter
     * @return self|null the reduced feature, or null when nothing matches
     */
    public function withScenariosMatching(TitleFilter $filter): ?self
    {
        $filter->enterContext($this->featureNode->title);

        try {
            $scenarios = array_values(array_filter(
                $this->featureNode->scenarios,
                fn(ScenarioNode $scenario) => $filter->matches($scenario->title),
            ));
        } finally {
            $filter->leaveContext();
        }

        return $scenarios === [] ? null : $this->withScenarios($scenarios);
    }

    /**
     * @param list<ScenarioNode> $scenarios
     */
    private function withScenarios(array $scenarios): self
    {
        $featureNode = new FeatureNode(
            $this->featureNode->title,
            $this->featureNode->description,
            $this->featureNode->background,
            $scenarios,
            $this->featureNode->tags,
        );

        return new self($this->path, $featureNode, $this->registry, $this->hooks);
    }

    /**
     * Yields each ScenarioResult as it completes.
     *
     * Pickles are already expanded (Scenario Outlines become individual scenarios,
     * Background steps are merged), so no expansion is needed here.
     *
     * @return \Generator<ScenarioResult> one result per scenario
     */
    public function stream(): \Generator
    {
        $held = $this->attempt(fn() => $this->hooks->runBeforeFeature(), 'beforeFeature');

        // The last scenario waits for afterFeature, which may break its last step.
        $last = null;
        $closed = false;

        try {
            foreach ($this->featureNode->scenarios as $scenario) {
                $expansions = $scenario instanceof ScenarioOutlineNode ? $scenario->expand() : [$scenario];

                foreach ($expansions as $expanded) {
                    $result = $this->runScenario($expanded, $held);
                    if ($last !== null) {
                        yield $last;
                    }
                    $last = $result;

                    if (StopRegistry::reached($result)) {
                        break 2;
                    }
                }
            }

            $closed = true;
            $after = $this->attempt(fn() => $this->hooks->runAfterFeature(), 'afterFeature');
            if ($last !== null) {
                yield $after === null ? $last : new ScenarioResult($last->getTitle(), self::brokenAtTheEnd($last->getResults(), $after), $last->getLine(), $last->getAttachments());
            }
        } finally {
            if (!$closed) {
                $this->attempt(fn() => $this->hooks->runAfterFeature(), 'afterFeature');
            }
        }
    }

    /**
     * Executes all scenarios in the feature, expanding outlines, and returns aggregate results.
     *
     * @return Results the FeatureResult containing all scenario outcomes
     */
    public function run(): Results
    {
        return new FeatureResult(
            $this->featureNode->title,
            iterator_to_array($this->stream(), false),
            $this->path,
        );
    }

    /**
     * Returns the filesystem path to the .feature file.
     *
     * @return string the absolute or relative path to the .feature file
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Runs a single scenario, including background steps, and collects step results.
     * Skips remaining steps after the first failure.
     *
     * @param ScenarioNode $scenario the scenario to execute
     * @param HookOutcome|null $featureHeld what a beforeFeature raised, deciding every scenario without running its hooks or steps
     */
    private function runScenario(ScenarioNode $scenario, ?HookOutcome $featureHeld = null): ScenarioResult
    {
        $world = new StepWorld();
        $held = $featureHeld ?? $this->attempt(fn() => $this->hooks->runBeforeScenario($world), 'beforeScenario');

        $collector = new StepMatchCollector();
        DispatcherRegistry::dispatcher()->addSubscriber($collector);

        // What the scenario hands over about itself, for as long as it runs: a
        // step attaches in one place and a later step's failure is what makes it
        // worth reading.
        $attachments = new Attachments();
        DispatcherRegistry::dispatcher()->addSubscriber($attachments);

        $stepResults = [];
        $failed = false;

        foreach ([...($this->featureNode->background->steps ?? []), ...$scenario->steps] as $step) {
            $title = $step->keyword . ' ' . $step->text;

            if ($held !== null) {
                $stepResults[] = $stepResults === [] ? $held->instead($title) : new StepResult($title, 'skipped');
                continue;
            }

            if ($failed) {
                $stepResults[] = new StepResult($title, 'skipped');
                continue;
            }

            $result = $this->runStep($step, $world, $collector);
            $after = $this->attempt(fn() => $this->hooks->runAfterStep($world), 'afterStep');
            $result = $after?->after($result) ?? $result;
            $stepResults[] = $result;
            if ($result->isFailure() || $result->isError() || $result->isSkipped()) {
                $failed = true;
            }
        }

        if ($held !== null && $stepResults === []) {
            $stepResults[] = $held->alone();
        }

        // Read before the teardown, and only for a scenario that needs
        // attention: an after-hook that stops the process and clears the
        // workspace would otherwise leave an empty attachment, which reads as
        // "it said nothing" rather than "PhpSpec looked too late".
        $attached = [];
        if (!$attachments->isEmpty() && self::needsAttention($stepResults)) {
            $attached = $attachments->read();
        }

        if ($featureHeld === null) {
            $after = $this->attempt(fn() => $this->hooks->runAfterScenario($world), 'afterScenario');
            if ($after !== null) {
                $stepResults = self::brokenAtTheEnd($stepResults, $after);
            }
        }

        DispatcherRegistry::dispatcher()->removeSubscriber($collector);
        DispatcherRegistry::dispatcher()->removeSubscriber($attachments);

        // An outline expansion is addressed by its own examples-table row; every
        // other scenario by its keyword line.
        return new ScenarioResult($scenario->title, $stepResults, $scenario->exampleLine ?? $scenario->line, $attached);
    }

    /**
     * Runs hooks, keeping what they raise instead of letting it end the run.
     */
    private function attempt(\Closure $hooks, string $name): ?HookOutcome
    {
        try {
            $hooks();

            return null;
        } catch (\Throwable $raised) {
            return new HookOutcome($raised, $name);
        }
    }

    /**
     * The steps with an after-hook's outcome on the last of them, or alone
     * when there is no step to carry it.
     *
     * @param array<Results> $steps
     * @return list<StepResult>
     */
    private static function brokenAtTheEnd(array $steps, HookOutcome $after): array
    {
        $steps = array_values(array_filter($steps, static fn(Results $step): bool => $step instanceof StepResult));
        $last = array_pop($steps);

        return $last === null ? [$after->alone()] : [...$steps, $after->after($last)];
    }

    /**
     * Whether any step left the scenario something to answer for.
     *
     * @param array<StepResult> $stepResults
     */
    private static function needsAttention(array $stepResults): bool
    {
        foreach ($stepResults as $step) {
            if (!$step->isPassed()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Executes a single step by matching it against the registry, running hooks,
     * and collecting match expectations. Returns undefined/pending/failed/passed status.
     *
     * @param StepNode $step the step node to execute
     * @param object $world shared state object bound as $this inside step closures
     * @param StepMatchCollector $collector shared match collector for the scenario
     * @throws PendingException caught internally and reported as pending
     */
    private function runStep(StepNode $step, object $world, StepMatchCollector $collector): StepResult
    {
        $title = $step->keyword . ' ' . $step->text;

        $before = $this->attempt(fn() => $this->hooks->runBeforeStep($world), 'beforeStep');
        if ($before !== null) {
            return $before->instead($title);
        }
        $match = $this->registry->match($step->text);

        if ($match === null) {
            return new StepResult($title, 'undefined');
        }

        // PHP warnings, deprecations and notices raised inside the step are
        // collected onto its result (the same net examples run under), so they
        // report under their kinds instead of leaking raw to the terminal.
        $caught = new CapturedNotes(OwnCode::here());
        $caught->listen();

        $start = hrtime(true);
        try {
            $result = $this->executeStep($step, $title, $match, $world, $collector);
        } finally {
            $caught->stop();
        }

        $result->setDuration((hrtime(true) - $start) / 1e9);
        $result->raised($caught->notes());

        return $result;
    }

    /**
     * Runs the matched step, keeping what it printed on its result.
     */
    private function executeStep(StepNode $step, string $title, StepMatch $match, object $world, StepMatchCollector $collector): StepResult
    {
        // What the step printed belongs to the step: a scenario that drives a
        // process of its own says everything it knows through that output.
        $printed = new CapturedOutput();
        $result = $this->outcomeOf($step, $title, $match, $world, $collector, $printed);
        $result->setOutput($printed->text());

        return $result;
    }

    /**
     * Runs the matched step body and settles its outcome: an expectation that
     * did not hold is a failure; a step whose code threw is an error.
     */
    private function outcomeOf(StepNode $step, string $title, StepMatch $match, object $world, StepMatchCollector $collector, CapturedOutput $printed): StepResult
    {
        try {
            $args = $match->args;
            if ($step->docString !== null) {
                $args[] = $step->docString;
            }
            if ($step->table !== null) {
                $args[] = $step->table;
            }
            $printed->around(fn() => $match->callback->call($world, ...$args));
        } catch (PendingException $e) {
            return new StepResult($title, 'pending', $e->getMessage());
        } catch (SkippedException $e) {
            return new StepResult($title, 'skipped', $e->getMessage());
        } catch (\Throwable $e) {
            $result = new StepResult($title, 'error');
            $result->setError(new StepError($e->getMessage(), $e));
            return $result;
        }

        $matchResults = $collector->evaluateMatches();

        foreach ($matchResults as $matchResult) {
            if ($matchResult->isFailure()) {
                $message = (string) $matchResult->getMessage();
                $result = new StepResult($title, 'failure');
                $ex = new \RuntimeException($message);
                $result->setError(new StepError($message, $ex));
                // The expectation itself travels with the result, so a reader is
                // handed the two values instead of the sentence about them.
                $result->setMatch($matchResult);
                return $result;
            }
        }

        return new StepResult($title, 'passed');
    }
}
