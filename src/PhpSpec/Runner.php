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

use PhpSpec\Result\SuiteResult;

/**
 * @internal
 * Orchestrates a spec suite run by delegating execution to the Suite.
 */
final class Runner
{
    /**
     * Runs the given suite and returns its results.
     *
     * @param Suite $suite loaded suite to execute
     * @param StopConditions $stop conditions under which to halt early
     * @param int|null $seed random seed for shuffling order
     */
    public function run(Suite $suite, StopConditions $stop = new StopConditions(), ?int $seed = null): SuiteResult
    {
        return $suite->run($stop, $seed);
    }

    /**
     * Streams spec results progressively from the given suite.
     *
     * @param Suite $suite loaded suite to execute
     * @param StopConditions $stop conditions under which to halt early
     * @param int|null $seed random seed for shuffling order
     * @return \Generator yields one Results per spec/feature
     */
    public function stream(Suite $suite, StopConditions $stop = new StopConditions(), ?int $seed = null): \Generator
    {
        return $suite->stream($stop, $seed);
    }

}
