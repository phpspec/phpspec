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

namespace PhpSpec;

use PhpSpec\EventDispatcher\Dispatcher;
use PhpSpec\EventDispatcher\DispatcherRegistry;
use PhpSpec\EventDispatcher\Event\SuiteFinished;
use PhpSpec\EventDispatcher\Event\SuiteStarted;
use PhpSpec\Result\ExampleResult;
use PhpSpec\Result\StepResult;
use PhpSpec\Result\SuiteResult;
use PhpSpec\Specification\SpecBlock;

/**
 * Top-level container that holds all specifications and orchestrates their execution.
 */
final readonly class Suite implements SpecBlock
{
    private Dispatcher $dispatcher;

    /**
     * @param array<SpecBlock> $specifications spec files and features to run
     * @param Dispatcher|null $dispatcher event dispatcher instance
     */
    public function __construct(
        private array $specifications,
        ?Dispatcher $dispatcher = null,
    ) {
        $this->dispatcher = $dispatcher ?? DispatcherRegistry::get();
    }

    /**
     * Returns all specifications and features registered in the suite.
     *
     * @return array<SpecBlock>
     */
    public function getSpecifications(): array
    {
        return $this->specifications;
    }

    /**
     * Yields each specification/feature result as it completes.
     *
     * @param StopConditions $stop conditions under which to halt execution early
     * @param int|null $seed random seed for shuffling execution order
     * @return \Generator<Results> one result per spec/feature
     */
    public function stream(StopConditions $stop = new StopConditions(), ?int $seed = null): \Generator
    {
        $this->dispatcher->dispatch(new SuiteStarted(), SuiteStarted::NAME);

        $specs = $this->specifications;
        if ($seed !== null) {
            mt_srand($seed);
            shuffle($specs);
        }

        foreach ($specs as $spec) {
            $result = $spec->run();
            yield $result;

            if ($stop->any() && $this->shouldStop($result, $stop)) {
                $this->dispatcher->dispatch(new SuiteFinished(), SuiteFinished::NAME);
                return;
            }
        }

        $this->dispatcher->dispatch(new SuiteFinished(), SuiteFinished::NAME);
    }

    /**
     * Runs all specifications and collects their results.
     *
     * @param StopConditions $stop conditions under which to halt execution early
     * @param int|null $seed random seed for shuffling execution order
     * @return SuiteResult aggregated suite result
     */
    public function run(StopConditions $stop = new StopConditions(), ?int $seed = null): SuiteResult
    {
        return new SuiteResult(iterator_to_array($this->stream($stop, $seed), false));
    }

    /**
     * Recursively checks whether a result tree should trigger a stop.
     *
     * @param Results $results result node to inspect
     * @param StopConditions $stop the active stop conditions
     */
    private function shouldStop(Results $results, StopConditions $stop): bool
    {
        foreach ($results->getResults() as $result) {
            if ($result instanceof ExampleResult) {
                if ($stop->onFailure && ($result->isFailure() || $result->isError())) {
                    return true;
                }
                if ($stop->onError && $result->isError()) {
                    return true;
                }
                if ($stop->onWarning && $result->hasWarnings()) {
                    return true;
                }
                if ($stop->onDeprecation && $result->hasDeprecations()) {
                    return true;
                }
                if ($stop->onNotice && $result->hasNotices()) {
                    return true;
                }
                if ($stop->onSkipped && $result->isSkipped()) {
                    return true;
                }
            } elseif ($result instanceof StepResult) {
                if ($stop->onFailure && $result->isFailure()) {
                    return true;
                }
            } elseif ($result instanceof Results) {
                if ($this->shouldStop($result, $stop)) {
                    return true;
                }
            }
        }
        return false;
    }
}
