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

use PhpSpec\EventDispatcher\DispatcherRegistry;
use PhpSpec\EventDispatcher\Event\SuiteFinished;
use PhpSpec\EventDispatcher\Event\SuiteStarted;
use PhpSpec\Result\SuiteResult;
use PhpSpec\Specification\SpecBlock;

/**
 * @internal
 * Top-level container that holds all specifications and orchestrates their execution.
 */
final readonly class Suite implements SpecBlock
{
    /**
     * @param array<SpecBlock> $specifications spec files and features to run
     */
    public function __construct(private array $specifications) {}

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
        DispatcherRegistry::dispatcher()->dispatch(new SuiteStarted(), SuiteStarted::NAME);

        $specs = $this->specifications;
        if ($seed !== null) {
            mt_srand($seed);
            shuffle($specs);
        }

        if ($stop->any()) {
            StopRegistry::activate($stop);
        }

        try {
            foreach ($specs as $spec) {
                $result = $spec->run();
                yield $result;

                if (StopRegistry::reached($result)) {
                    break;
                }
            }
        } finally {
            StopRegistry::reset();
        }

        DispatcherRegistry::dispatcher()->dispatch(new SuiteFinished(), SuiteFinished::NAME);
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

}
