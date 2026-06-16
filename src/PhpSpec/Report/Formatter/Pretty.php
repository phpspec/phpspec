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

namespace PhpSpec\Report\Formatter;

use PhpSpec\Report\AbstractFormatter;
use PhpSpec\Report\Formatter\Pretty\PrettyViews;
use PhpSpec\Result\Counts;
use PhpSpec\Result\FeatureResult;
use PhpSpec\Result\SpecificationResult;
use PhpSpec\Result\SuiteResult;
use PhpSpec\Results;

/**
 * @internal
 * Renders spec results using typed static methods for human-readable, indented output.
 */
final class Pretty extends AbstractFormatter
{
    private bool $hasResults = false;

    /**
     * No-op; the banner is deferred to the first printResult call.
     */
    public function begin(): void
    {
        // Banner is deferred to first printResult call
    }

    /**
     * Renders a single specification or feature result.
     *
     * @param Results $result one spec or feature result to render
     */
    public function printResult(Results $result): void
    {
        if (!$this->hasResults) {
            $this->output->writeln('Once you spec, you never go back!');
            $this->hasResults = true;
        }

        if ($result instanceof FeatureResult) {
            PrettyViews::feature($this->output, $result);
        } elseif ($result instanceof SpecificationResult) {
            PrettyViews::specification($this->output, $result, $this->output->isVerbose());
        }
        $this->output->writeln('');
    }

    /**
     * Outputs error details and summary counts after all results have been rendered.
     *
     * @param SuiteResult $results the complete suite results for final summary
     */
    public function end(SuiteResult $results): void
    {
        if (!$this->hasResults) {
            $this->output->writeln('No specs found.');
            return;
        }

        foreach ($results->getResults() as $specificationResult) {
            if ($specificationResult instanceof FeatureResult) {
                PrettyViews::featureErrors($this->output, $specificationResult);
            } elseif ($specificationResult instanceof SpecificationResult) {
                PrettyViews::specificationErrors($this->output, $specificationResult);
            }
        }

        $this->output->writeln('');

        $counts = new Counts($results);
        PrettyViews::counts($this->output, $counts->toArray(), $results->getDuration());
    }
}
