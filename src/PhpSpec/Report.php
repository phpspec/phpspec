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
 * Contract for outputting spec run results to the console or other targets.
 */
interface Report
{
    /**
     * Formats and outputs the given results.
     *
     * @param SuiteResult $results aggregated results from a suite run
     */
    public function print(SuiteResult $results): void;
}
