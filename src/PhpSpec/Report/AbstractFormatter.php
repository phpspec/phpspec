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
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 * Base formatter that provides a default format() implementation
 * by delegating to the streaming begin/printResult/end methods.
 */
abstract class AbstractFormatter implements Formatter
{
    /**
     * @param OutputInterface $output the console output to write to
     */
    public function __construct(protected OutputInterface $output) {}

    /**
     * Default batch format: delegates to begin(), printResult() for each result, then end().
     */
    public function format(SuiteResult $results): void
    {
        $this->begin();
        foreach ($results->getResults() as $result) {
            $this->printResult($result);
        }
        $this->end($results);
    }
}
