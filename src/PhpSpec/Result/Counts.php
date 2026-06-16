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

namespace PhpSpec\Result;

use PhpSpec\Results;

/**
 * @internal
 * Traverses a result tree to produce summary statistics (passes, failures, errors, pending, etc.).
 *
 * Recursively walks SpecificationResult, ContextResult, ExampleResult, FeatureResult,
 * ScenarioResult, and StepResult nodes to tally counts for reporting.
 */
final class Counts
{
    private int $examples = 0;
    private int $passes = 0;
    private int $failures = 0;
    private int $errors = 0;
    private int $broken = 0;
    private int $pending = 0;
    private int $warnings = 0;
    private int $deprecations = 0;
    private int $notices = 0;
    private int $exampleSkipped = 0;
    private int $features = 0;
    private int $scenarios = 0;
    private int $steps = 0;
    private int $stepPasses = 0;
    private int $stepFailures = 0;
    private int $undefined = 0;
    private int $skipped = 0;

    /**
     * @param Results $results the root result node to count from
     */
    public function __construct(private readonly Results $results) {}

    /**
     * Traverses the result tree and returns an associative array of all tallied counts.
     *
     * @return array<string, int> keyed by category name (specs, examples, passes, etc.)
     */
    public function toArray(): array
    {
        $this->count($this->results);
        $specs = count($this->results->getResults());

        return [
            'specs' => $specs,
            'examples' => $this->examples,
            'passes' => $this->passes,
            'failures' => $this->failures,
            'errors' => $this->errors,
            'broken' => $this->broken,
            'pending' => $this->pending,
            'warnings' => $this->warnings,
            'deprecations' => $this->deprecations,
            'notices' => $this->notices,
            'exampleSkipped' => $this->exampleSkipped,
            'features' => $this->features,
            'scenarios' => $this->scenarios,
            'steps' => $this->steps,
            'stepPasses' => $this->stepPasses,
            'stepFailures' => $this->stepFailures,
            'undefined' => $this->undefined,
            'skipped' => $this->skipped,
        ];
    }

    /**
     * Recursively counts results by type, incrementing the appropriate tallies.
     *
     * @param mixed $result a result node to process
     */
    private function count($result): void
    {
        if ($result instanceof ExampleResult) {
            $this->examples++;
            if ($result->hasWarnings()) {
                $this->warnings++;
            }
            if ($result->hasDeprecations()) {
                $this->deprecations++;
            }
            if ($result->hasNotices()) {
                $this->notices++;
            }
            if ($result->isPending()) {
                $this->pending++;
            } elseif ($result->isSkipped()) {
                $this->exampleSkipped++;
            } elseif ($result->isError()) {
                $this->errors++;
            } elseif ($result->isFailure()) {
                $this->failures++;
            } else {
                $this->passes++;
            }
        } elseif ($result instanceof StepResult) {
            $this->steps++;
            if ($result->isPassed()) {
                $this->stepPasses++;
            } elseif ($result->isFailure()) {
                $this->stepFailures++;
            } elseif ($result->isPending()) {
                $this->pending++;
            } elseif ($result->isUndefined()) {
                $this->undefined++;
            } elseif ($result->isSkipped()) {
                $this->skipped++;
            }
        } elseif ($result instanceof FeatureResult) {
            $this->features++;
            foreach ($result->getResults() as $child) {
                $this->count($child);
            }
        } elseif ($result instanceof ScenarioResult) {
            $this->scenarios++;
            foreach ($result->getResults() as $child) {
                $this->count($child);
            }
        } else {
            foreach ($result->getResults() as $child) {
                $this->count($child);
            }
        }
    }
}
