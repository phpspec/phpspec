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

namespace PhpSpec\Report;

use PhpSpec\Result\SuiteResult;
use PhpSpec\Results;

/**
 * Defines the contract for output formatters that render spec run results.
 */
interface Formatter
{
    /**
     * Formats and outputs the spec run results.
     *
     * @param SuiteResult $results the complete suite results to render
     */
    public function format(SuiteResult $results): void;

    /**
     * Outputs any header/banner before results start streaming.
     */
    public function begin(): void;

    /**
     * Outputs a single specification or feature result as it completes.
     *
     * @param Results $result one spec/feature result
     */
    public function printResult(Results $result): void;

    /**
     * Outputs the summary (errors, counts, duration) after all results have streamed.
     *
     * @param SuiteResult $results the complete suite results for final summary
     */
    public function end(SuiteResult $results): void;
}
