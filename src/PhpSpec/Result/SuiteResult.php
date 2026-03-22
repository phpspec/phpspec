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
 * Top-level result that aggregates all specification and feature results for the entire suite.
 *
 * Provides overall pass/fail status, suite duration, and profiling support for
 * identifying the slowest examples.
 */
final class SuiteResult implements Results
{
    /** @var array<Results> SpecificationResult, FeatureResult, and other Results instances */
    private array $specificationResults;

    /** @var float Total suite execution duration in seconds */
    private float $duration = 0.0;

    /**
     * @param array<Results> $results array of SpecificationResult, FeatureResult, and other Results instances
     */
    public function __construct(array $results)
    {
        $this->specificationResults = $results;
    }

    /**
     * Records the total suite execution duration.
     *
     * @param float $duration elapsed time in seconds
     */
    public function setDuration(float $duration): void
    {
        $this->duration = $duration;
    }

    /**
     * Returns the total suite execution duration in seconds.
     */
    public function getDuration(): float
    {
        return $this->duration;
    }

    /**
     * Returns the exit status code: 0 for success, 1 if any failures or errors occurred.
     */
    public function status(): int
    {
        $counts = new Counts($this);
        $c = $counts->toArray();
        return ($c['failures'] > 0 || $c['errors'] > 0 || $c['stepFailures'] > 0) ? 1 : 0;
    }

    /**
     * Returns all specification and feature results.
     */
    public function getResults(): array
    {
        return $this->specificationResults;
    }

    /**
     * Checks whether the suite contains no results.
     */
    public function isEmpty(): bool
    {
        return count($this->specificationResults) === 0;
    }

    /**
     * Recursively collects all ExampleResult instances from the result tree.
     *
     * @return ExampleResult[]
     */
    public function getAllExamples(): array
    {
        $examples = [];
        $this->collectExamples($this, $examples);
        return $examples;
    }

    /**
     * Returns the slowest examples sorted by duration descending, for profiling output.
     *
     * @param int $count maximum number of examples to return
     * @return ExampleResult[] the slowest non-pending examples
     */
    public function getSlowestExamples(int $count = 10): array
    {
        $examples = array_filter($this->getAllExamples(), fn(ExampleResult $e) => !$e->isPending());
        usort($examples, fn(ExampleResult $a, ExampleResult $b) => $b->getDuration() <=> $a->getDuration());
        return array_slice($examples, 0, $count);
    }

    /**
     * Recursively collects ExampleResult instances from the result tree.
     *
     * @param Results $results the result node to traverse
     * @param ExampleResult[] $examples collected examples, passed by reference
     */
    private function collectExamples(Results $results, array &$examples): void
    {
        foreach ($results->getResults() as $result) {
            if ($result instanceof ExampleResult) {
                $examples[] = $result;
            } elseif ($result instanceof Results) {
                $this->collectExamples($result, $examples);
            }
        }
    }
}
