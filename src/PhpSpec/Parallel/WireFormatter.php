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

namespace PhpSpec\Parallel;

use PhpSpec\Report\AbstractFormatter;
use PhpSpec\Result\FeatureResult;
use PhpSpec\Result\SpecificationResult;
use PhpSpec\Result\SuiteResult;
use PhpSpec\Results;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 * What a parallel worker runs with: each spec or feature result goes out on
 * its own line as it completes, whole, and a last line says the report is
 * done, so the parent can tell a worker that finished from one that died.
 */
final class WireFormatter extends AbstractFormatter
{
    private readonly Wire $wire;

    public function __construct(OutputInterface $output)
    {
        parent::__construct($output);
        $this->wire = new Wire();
    }

    public function begin(): void {}

    public function printResult(Results $result): void
    {
        if ($result instanceof SpecificationResult || $result instanceof FeatureResult) {
            $this->output->write($this->wire->encode($result) . "\n", false, OutputInterface::OUTPUT_RAW);
        }
    }

    public function end(SuiteResult $results): void
    {
        $this->output->write($this->wire->ending() . "\n", false, OutputInterface::OUTPUT_RAW);
    }
}
