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

namespace PhpSpec\Extensions;

use PhpSpec\Report\Formatter;
use PhpSpec\Result\Outcome;
use PhpSpec\Result\Outcomes;
use PhpSpec\Result\SuiteResult;
use PhpSpec\Results;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 * Adapts a FormatterExtension into the Formatter interface used by the runner:
 * every example and scenario reaches it as an Outcome, and the summary is
 * made of the outcomes it was handed.
 */
final class FormatterBridge implements Formatter
{
    /** @var list<Outcome> */
    private array $outcomes = [];

    public function __construct(
        private readonly FormatterExtension $extension,
        private readonly OutputInterface $output,
        private readonly Outcomes $walker,
    ) {}

    public function format(SuiteResult $results): void
    {
        $this->begin();
        foreach ($results->getResults() as $result) {
            $this->printResult($result);
        }
        $this->end($results);
    }

    public function begin(): void
    {
        $this->output->writeln('');
    }

    public function printResult(Results $result): void
    {
        $this->walker->walk($result, function (Outcome $outcome): void {
            $this->outcomes[] = $outcome;
            $this->output->writeln($this->extension->format($outcome));
        });
    }

    public function end(SuiteResult $results): void
    {
        $this->output->writeln('');
        $this->output->writeln($this->extension->formatSummary($this->outcomes));

        $duration = $results->getDuration();
        if ($duration > 0) {
            $this->output->writeln(sprintf('Finished in %.4f seconds', $duration));
        }
    }
}
