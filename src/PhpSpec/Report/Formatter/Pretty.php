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
use PhpSpec\Report\TemplateRenderer;
use PhpSpec\Result\Counts;
use PhpSpec\Result\FeatureResult;
use PhpSpec\Result\SuiteResult;
use PhpSpec\Results;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Renders spec results using PHP view templates for human-readable, indented output.
 * Delegates rendering to TemplateRenderer with the suite view hierarchy.
 */
final class Pretty extends AbstractFormatter
{
    private bool $hasResults = false;

    private TemplateRenderer $template;

    /**
     * @param OutputInterface $output the console output to write to
     */
    public function __construct(OutputInterface $output)
    {
        parent::__construct($output);
        $this->template = new TemplateRenderer(
            __DIR__ . DIRECTORY_SEPARATOR . 'Pretty' . DIRECTORY_SEPARATOR . 'views',
            $this->output,
        );
    }

    /**
     * No-op; the banner is deferred to the first printResult call.
     */
    public function begin(): void
    {
        // Banner is deferred to first printResult call
    }

    /**
     * Renders a single specification or feature result using PHP view templates.
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
            $this->template->render('feature', ['feature' => $result]);
        } else {
            $this->template->render('specification', ['specification' => $result, 'verbose' => $this->output->isVerbose()]);
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
                $this->template->render('feature.errors', ['feature' => $specificationResult]);
            } else {
                $this->template->render('specification.errors', ['specification' => $specificationResult]);
            }
        }

        $this->output->writeln('');

        $counts = new Counts($results);
        $this->template->render('counts', ['counts' => $counts->toArray(), 'duration' => $results->getDuration()]);
    }
}
