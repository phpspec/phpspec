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
 * its own line as it completes, whole; a line says when the worker ran the
 * file it was handed, and a last one that the report is done, so the parent
 * can tell a worker that finished from one that died.
 */
final class WireFormatter extends AbstractFormatter
{
    private readonly Wire $wire;

    private string $held = '';

    public function __construct(OutputInterface $output)
    {
        parent::__construct($output);
        $this->wire = new Wire();
    }

    public function begin(): void {}

    public function printResult(Results $result): void
    {
        if ($result instanceof SpecificationResult || $result instanceof FeatureResult) {
            $this->send('');
            $this->held = $this->wire->encode($result) . "\n";
        }
    }

    public function end(SuiteResult $results): void
    {
        $this->send($this->wire->ending() . "\n");
    }

    /**
     * Says the worker ran the file it was handed, so it is handed the next.
     */
    public function ran(): void
    {
        $this->send($this->wire->ran() . "\n");
    }

    /**
     * Writes the result held back along with what follows it, in one write:
     * a parent that read the result alone would report it before it could
     * hand the worker its next file.
     */
    private function send(string $line): void
    {
        if ($this->held . $line !== '') {
            $this->output->write($this->held . $line, false, OutputInterface::OUTPUT_RAW);
        }

        $this->held = '';
    }
}
