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

namespace PhpSpec\Report\Cli;

use PhpSpec\Report as BaseReport;
use PhpSpec\Report\Formatter;
use PhpSpec\Result\SuiteResult;

/**
 * CLI report implementation that delegates rendering to a pluggable Formatter.
 */
final class Report implements BaseReport
{
    /** @var Formatter */
    private Formatter $formatter;

    /**
     * Sets the formatter used for rendering output.
     *
     * @param Formatter $formatter the formatter to delegate to
     */
    public function setFormatter(Formatter $formatter): void
    {
        $this->formatter = $formatter;
    }

    /**
     * Prints the spec run results by delegating to the configured formatter.
     *
     * @param SuiteResult $results the complete suite results to print
     */
    public function print(SuiteResult $results): void
    {
        $this->formatter->format($results);
    }
}
