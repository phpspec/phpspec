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

use PhpSpec\EventDispatcher\DispatcherRegistry;
use PhpSpec\Result\FeatureResult;
use PhpSpec\Result\ScenarioResult;
use PhpSpec\Result\StepResult;
use PhpSpec\Results;
use PhpSpec\Specification\PendingException;
use PhpSpec\Specification\SkippedException;
use PhpSpec\Specification\SpecBlock;

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

        $stepResults = [];
        $failed = false;

        if ($this->featureNode->background !== null) {
            foreach ($this->featureNode->background->steps as $step) {
                if ($failed) {
                    $stepResults[] = new StepResult($step->keyword . ' ' . $step->text, 'skipped');
                    continue;
                }
                $result = $this->runStep($step, $world, $collector);
                $stepResults[] = $result;
                if ($result->isFailure() || $result->isSkipped()) {
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
            $stepResults[] = $result;
            if ($result->isFailure() || $result->isSkipped()) {
                $failed = true;
            }
        }

        DispatcherRegistry::dispatcher()->removeSubscriber($collector);

        return new ScenarioResult($scenario->title, $stepResults);
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

        try {
            $args = $match->args;
            if ($step->docString !== null) {
                $args[] = $step->docString;
            }
            if ($step->table !== null) {
                $args[] = $step->table;
            }
            $match->callback->call($world, ...$args);
        } catch (PendingException $e) {
            return new StepResult($title, 'pending');
        } catch (SkippedException $e) {
            return new StepResult($title, 'skipped');
        } catch (\Throwable $e) {
            $result = new StepResult($title, 'failure');
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
                return $result;
            }
        }

        return new StepResult($title, 'passed');
    }
}
