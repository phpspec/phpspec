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
use PhpSpec\CapturedOutput;
use PhpSpec\EventDispatcher\DispatcherRegistry;
use PhpSpec\Result\FeatureResult;
use PhpSpec\Result\ScenarioResult;
use PhpSpec\Result\StepResult;
use PhpSpec\Results;
use PhpSpec\Specification\PendingException;
use PhpSpec\Specification\SkippedException;
use PhpSpec\Specification\SpecBlock;
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
     * Returns a copy of this feature reduced to the scenarios whose title
     * matches the filter, or null when no scenario matches.
     *
     * @param TitleFilter $filter the active title filter
     * @return self|null the reduced feature, or null when nothing matches
     */
    public function withScenariosMatching(TitleFilter $filter): ?self
    {
        $scenarios = array_values(array_filter(
            $this->featureNode->scenarios,
            fn(ScenarioNode $scenario) => $filter->matches($scenario->title),
        ));

        if ($scenarios === []) {
            return null;
        }

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
        $this->hooks->runBeforeFeature();

        foreach ($this->featureNode->scenarios as $scenario) {
            if ($scenario instanceof ScenarioOutlineNode) {
                foreach ($scenario->expand() as $expanded) {
                    yield $this->runScenario($expanded);
                }
            } else {
                yield $this->runScenario($scenario);
            }
        }

        $this->hooks->runAfterFeature();
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
     */
    private function runScenario(ScenarioNode $scenario): ScenarioResult
    {
        $world = new StepWorld();
        $this->hooks->runBeforeScenario($world);

        $collector = new StepMatchCollector();
        DispatcherRegistry::dispatcher()->addSubscriber($collector);

        // What the scenario hands over about itself, for as long as it runs: a
        // step attaches in one place and a later step's failure is what makes it
        // worth reading.
        $attachments = new Attachments();
        DispatcherRegistry::dispatcher()->addSubscriber($attachments);

        $stepResults = [];
        $failed = false;

        if ($this->featureNode->background !== null) {
            foreach ($this->featureNode->background->steps as $step) {
                if ($failed) {
                    $stepResults[] = new StepResult($step->keyword . ' ' . $step->text, 'skipped');
                    continue;
                }
                $result = $this->runStep($step, $world, $collector);
                $this->hooks->runAfterStep($world);
                $stepResults[] = $result;
                if ($result->isFailure() || $result->isError() || $result->isSkipped()) {
                    $failed = true;
                }
            }
        }

        foreach ($scenario->steps as $step) {
            if ($failed) {
                $stepResults[] = new StepResult($step->keyword . ' ' . $step->text, 'skipped');
                continue;
            }
            $result = $this->runStep($step, $world, $collector);
            $this->hooks->runAfterStep($world);
            $stepResults[] = $result;
            if ($result->isFailure() || $result->isError() || $result->isSkipped()) {
                $failed = true;
            }
        }

        // Read before the teardown, and only for a scenario that needs
        // attention: an after-hook that stops the process and clears the
        // workspace would otherwise leave an empty attachment, which reads as
        // "it said nothing" rather than "PhpSpec looked too late".
        $attached = [];
        if (!$attachments->isEmpty() && self::needsAttention($stepResults)) {
            $attached = $attachments->read();
        }

        $this->hooks->runAfterScenario($world);

        DispatcherRegistry::dispatcher()->removeSubscriber($collector);
        DispatcherRegistry::dispatcher()->removeSubscriber($attachments);

        // An outline expansion is addressed by its own examples-table row; every
        // other scenario by its keyword line.
        return new ScenarioResult($scenario->title, $stepResults, $scenario->exampleLine ?? $scenario->line, $attached);
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
        $this->hooks->runBeforeStep($world);

        $title = $step->keyword . ' ' . $step->text;
        $match = $this->registry->match($step->text);

        if ($match === null) {
            return new StepResult($title, 'undefined');
        }

        // PHP warnings and notices raised inside the step are collected onto
        // its result (the same net examples run under), so they report in the
        // Warnings section instead of leaking raw to the terminal.
        $warnings = [];
        set_error_handler(function (int $severity, string $message, string $file, int $line) use (&$warnings) {
            $warnings[] = [
                'severity' => $severity,
                'message' => $message,
                'file' => $file,
                'line' => $line,
            ];
            return true;
        }, E_WARNING | E_NOTICE | E_DEPRECATED | E_USER_WARNING | E_USER_NOTICE | E_USER_DEPRECATED);

        try {
            $result = $this->executeStep($step, $title, $match, $world, $collector);
        } finally {
            restore_error_handler();
        }

        $result->setWarnings($warnings);

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
            return new StepResult($title, 'pending');
        } catch (SkippedException $e) {
            return new StepResult($title, 'skipped');
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
